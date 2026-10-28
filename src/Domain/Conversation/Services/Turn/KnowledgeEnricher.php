<?php

namespace Domain\Conversation\Services\Turn;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Turn\KnowledgeLookupData;
use Domain\Conversation\Data\Turn\QaMatchData;
use Domain\Conversation\Data\Turn\RetrievedSourceData;
use Domain\Conversation\Services\ChatContext;
use Domain\Guardrail\Services\GuardrailInspector;
use Domain\Guardrail\Services\GuardrailRecorder;
use Domain\Knowledge\Ingestion\Services\ChunkImageLink;
use Domain\Knowledge\Retrieval\Data\RetrievedChunkData;
use Domain\Knowledge\Retrieval\Data\SourceRefData;
use Domain\Knowledge\Retrieval\Jobs\UpdateRetrievalStatsJob;
use Domain\Knowledge\Retrieval\Services\KnowledgeBase;
use Domain\Knowledge\Retrieval\Services\KnowledgeScope;
use Domain\Knowledge\Retrieval\Services\KnowledgeSearchSwitch;
use Domain\Knowledge\Retrieval\Services\SourceLabeler;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;
use Domain\Knowledge\Source\Qa\Services\QaMatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Adds the agent's knowledge to a turn. A curated Q&A entry with a question
 * equal to the message as a whole (ignoring case and punctuation), or very
 * close to it in meaning, wins and answers the turn without an LLM call.
 * Otherwise the knowledge base is searched, retrieved chunks that look like a
 * prompt injection are dropped, and the rest is attached to the LLM-facing
 * message as reference material. The turn's confidence is the relevance of
 * its best chunk, and the retrieval counters of the sources it used are
 * bumped off the request path (eval runs excepted). An agent that does not
 * search its knowledge ({@see KnowledgeSearchSwitch}) gets none of this.
 */
class KnowledgeEnricher
{
    /** Confidence given to a Q&A match. */
    public const float QA_MATCH_CONFIDENCE = 0.99;

    /** The request source of eval runs, which leave the retrieval counters alone. */
    private const string EVAL_SOURCE = 'eval';

    public function __construct(
        private readonly KnowledgeBase $knowledgeBase,
        private readonly SourceLabeler $labeler,
        private readonly QaMatcher $qaMatcher,
        private readonly GuardrailInspector $guardrails,
        private readonly GuardrailRecorder $guardrailRecorder,
        private readonly KnowledgeScope $scope,
        private readonly KnowledgeSearchSwitch $knowledgeSearch,
    ) {}

    public function enrich(ChatContext $context): ChatContext
    {
        // A guard-blocked turn never reaches the LLM, so the (costly)
        // retrieval is skipped entirely. So is it for an agent that does not
        // search its knowledge: no Q&A answer, no search, no no-match note.
        if ($context->guardBlocked || ! $this->knowledgeSearch->for($context->requestData->agent)) {
            return $context;
        }

        $agent = $context->requestData->agent;

        $qaMatch = $this->findQaMatch($agent, $context->payload->message, $context->searchQuery ?? $context->payload->message);

        if ($qaMatch instanceof QaMatchData) {
            $context->setQaMatch($qaMatch);
            $context->setConfidenceScore($qaMatch->confidenceScore);

            return $context;
        }

        $sources = $this->withoutInjectedSources($context, $this->retrieveSources($agent, $context->searchQuery ?? $context->payload->message_for_llm ?? ''));
        $context->setSources($sources);

        if ($sources->isEmpty()) {
            return $this->withNoMatchNote($context);
        }

        $this->countRetrieval($context, $sources);

        return $this->withReferenceMaterial($context, $sources);
    }

    /**
     * What the knowledge holds for a question the model looks up itself (a
     * voice call has no reference material pasted in; its model calls the
     * knowledge search tool): a Q&A answer, else the chunks that pass the
     * relevance check and the injection scan. The retrieval counters of the
     * sources used are bumped as on a typed turn.
     */
    public function lookup(ChatContext $context, string $query): KnowledgeLookupData
    {
        $agent = $context->requestData->agent;

        if (! $this->knowledgeSearch->for($agent)) {
            return new KnowledgeLookupData(qaMatch: null, sources: collect());
        }

        $qaMatch = $this->findQaMatch($agent, $query, $query);

        if ($qaMatch instanceof QaMatchData) {
            return new KnowledgeLookupData(qaMatch: $qaMatch, sources: collect());
        }

        $sources = $this->withoutInjectedSources($context, $this->retrieveSources($agent, $query));

        if ($sources->isNotEmpty()) {
            $this->countRetrieval($context, $sources);
        }

        return new KnowledgeLookupData(qaMatch: null, sources: $sources);
    }

    /** The Q&A entry that answers the message, as a whole question or by meaning ({@see QaMatcher}). */
    private function findQaMatch(Agent $agent, string $message, string $searchQuery): ?QaMatchData
    {
        $match = $this->qaMatcher->match($agent, $message, $searchQuery);

        if (! $match instanceof AgentQuestionAnswer) {
            return null;
        }

        return new QaMatchData(
            id: $match->id,
            questions: $match->questions,
            answer: $match->answer,
            confidenceScore: self::QA_MATCH_CONFIDENCE,
            title: $match->title,
        );
    }

    /**
     * Always search: the relevance check drops chunks that do not really
     * match, so greetings come back empty without a separate gate.
     *
     * @return Collection<int, RetrievedSourceData>
     */
    private function retrieveSources(Agent $agent, string $query): Collection
    {
        $chunks = $this->knowledgeBase->search($agent, $query);

        if ($chunks === []) {
            return collect();
        }

        $labels = $this->labeler->forChunks($chunks);

        return collect($chunks)->map(fn (RetrievedChunkData $chunk): RetrievedSourceData => new RetrievedSourceData(
            id: $chunk->id,
            external_id: $chunk->external_id,
            content: $chunk->chunk,
            relevance: $chunk->relevance,
            label: $labels[$chunk->id]->label,
            kind: $labels[$chunk->id]->kind,
            url: $labels[$chunk->id]->url,
            page: $labels[$chunk->id]->page,
            sourceable_type: $chunk->chunkable_type,
            sourceable_id: $chunk->chunkable_id,
            images: $chunk->images(),
            added_on: $chunk->added_on,
        ));
    }

    /**
     * Bump the retrieval counters of the sources the turn used, off the
     * request path. Only source references go on the queue, never chunk text
     * (customer content would sit in Redis).
     *
     * @param  Collection<int, RetrievedSourceData>  $sources
     */
    private function countRetrieval(ChatContext $context, Collection $sources): void
    {
        if ($context->requestData->source === self::EVAL_SOURCE) {
            return;
        }

        UpdateRetrievalStatsJob::dispatch(
            $sources
                ->map(static fn (RetrievedSourceData $source): SourceRefData => new SourceRefData($source->sourceable_type, $source->sourceable_id))
                ->unique(static fn (SourceRefData $reference): string => "{$reference->type}:{$reference->id}")
                ->values(),
            $context->requestData->agent->id,
        );
    }

    /**
     * Drop retrieved chunks that trip the source injection guard, logging
     * each one, so a poisoned document never reaches the model's prompt.
     *
     * @param  Collection<int, RetrievedSourceData>  $sources
     * @return Collection<int, RetrievedSourceData>
     */
    private function withoutInjectedSources(ChatContext $context, Collection $sources): Collection
    {
        $agent = $context->requestData->agent;

        if (! $context->requestData->guardrailsEnabled || ! $this->guardrails->enabledFor($agent)) {
            return $sources;
        }

        return $sources->reject(function (RetrievedSourceData $source) use ($agent, $context): bool {
            $scan = $this->guardrails->scanSource($agent, $source->content);

            if (! $scan->triggered()) {
                return false;
            }

            $this->guardrailRecorder->record($context, $source->content, $scan);

            return true;
        })->values();
    }

    /**
     * A copy of the context whose LLM-facing message carries the reference
     * material, with the sources and their confidence.
     *
     * @param  Collection<int, RetrievedSourceData>  $sources
     */
    private function withReferenceMaterial(ChatContext $context, Collection $sources): ChatContext
    {
        $enriched = $this->withMessageForLlm($context, $this->referenceMaterial($context->payload->message_for_llm ?? '', $sources));

        $enriched->setSources($sources);
        $enriched->setConfidenceScore($this->confidence($sources));

        return $enriched;
    }

    /**
     * When nothing in the knowledge matched: a note that says so and, with
     * the admin's switches on, either the question to ask a visitor whose
     * message is too vague to search, or what the agent's knowledge covers,
     * so the model can say what it can help with. The context is unchanged
     * when neither applies, or the agent has no knowledge at all.
     */
    private function withNoMatchNote(ChatContext $context): ChatContext
    {
        $clarifyingQuestion = filter_var(config('chatvia.ai.query.rewriter.clarify'), FILTER_VALIDATE_BOOL)
            ? $context->clarifyingQuestion
            : null;
        $scope = $clarifyingQuestion === null && filter_var(config('chatvia.vector.search.scope_hint'), FILTER_VALIDATE_BOOL)
            ? $this->scope->names($context->requestData->agent)
            : [];

        if ($clarifyingQuestion === null && $scope === []) {
            return $context;
        }

        return $this->withMessageForLlm($context, prompt('Knowledge/no-match', [
            'question' => $context->payload->message_for_llm ?? '',
            'clarifyingQuestion' => $clarifyingQuestion,
            'scope' => $scope,
        ]));
    }

    /**
     * A copy of the context whose LLM-facing message is $message. Only that
     * message changes; the payload is cloned, so a field added later can
     * never be dropped by a hand-written rebuild.
     */
    private function withMessageForLlm(ChatContext $context, string $message): ChatContext
    {
        return new ChatContext(
            requestData: $context->requestData,
            payload: $context->payload->withMessageForLlm($message),
            llmMessages: $context->llmMessages,
            userMessageId: $context->userMessageId,
            assistantMessageId: $context->assistantMessageId,
            queryLanguage: $context->queryLanguage,
            startTime: $context->getStartTime(),
            searchQuery: $context->searchQuery,
            clarifyingQuestion: $context->clarifyingQuestion,
        );
    }

    /**
     * The relevance of the best chunk (the sources come best first); null
     * when no relevance check ran, so nothing can be said.
     *
     * @param  Collection<int, RetrievedSourceData>  $sources
     */
    public function confidence(Collection $sources): ?float
    {
        $relevance = $sources->first()?->relevance;

        return $relevance === null ? null : round(min(max($relevance, 0.0), 1.0), 4);
    }

    /**
     * The visitor's question followed by the retrieved chunks, each in its own
     * numbered `<source>` block dated with the day it entered the knowledge
     * base (so the model can prefer the newer of two that disagree), with a
     * signed link for every image so the model can show them. A chunk cannot close its block early: a closing
     * tag inside it is escaped.
     *
     * @param  Collection<int, RetrievedSourceData>  $sources
     */
    private function referenceMaterial(string $question, Collection $sources): string
    {
        return prompt('Knowledge/reference-material', [
            'question' => $question,
            'hasImages' => $sources->contains(static fn (RetrievedSourceData $source): bool => $source->images !== []),
            'items' => $sources->values()->map(fn (RetrievedSourceData $source, int $index): array => [
                'id' => $index + 1,
                'added' => $source->added_on,
                'content' => Str::replaceMatches('/<(\/?source)\b/i', '&lt;$1', $source->content) ?? '',
                'images' => $this->imageLinks($source),
            ])->all(),
        ]);
    }

    /**
     * @return array<int, array{description: string, url: string}>
     */
    private function imageLinks(RetrievedSourceData $source): array
    {
        $links = [];

        foreach (array_values($source->images) as $index => $description) {
            $links[] = [
                // The description is LLM-written alt text from an ingested
                // image, so it is attacker-influenceable: strip the markdown
                // delimiters so it cannot break out of the alt text and add
                // another image URL.
                'description' => Str::replace([']', '[', '(', ')', "\n", "\r"], ' ', (string) $description),
                // Always APP_URL, whichever host the chat was opened on. The path
                // carries an HMAC over (chunk, index), so the public image route
                // only serves links the server minted.
                'url' => Str::rtrim((string) config('app.url'), '/').ChunkImageLink::path($source->external_id ?? '', $index),
            ];
        }

        return $links;
    }
}
