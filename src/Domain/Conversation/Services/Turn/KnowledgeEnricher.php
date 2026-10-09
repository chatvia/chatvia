<?php

namespace Domain\Conversation\Services\Turn;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Turn\QaMatchData;
use Domain\Conversation\Data\Turn\RetrievedSourceData;
use Domain\Conversation\Services\ChatContext;
use Domain\Guardrail\Services\GuardrailRecorder;
use Domain\Guardrail\Services\GuardrailService;
use Domain\Knowledge\Retrieval\Data\RetrievedChunkData;
use Domain\Knowledge\Retrieval\Services\KnowledgeBase;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Support\Http\ChunkImageLink;

/**
 * Adds the agent's knowledge to a turn. A curated Q&A entry whose question
 * matches the message exactly wins and answers the turn without an LLM call.
 * Otherwise the knowledge base is searched, retrieved chunks that look like a
 * prompt injection are dropped, and the rest is attached to the LLM-facing
 * message as reference material.
 */
class KnowledgeEnricher
{
    /** Confidence given to an exact Q&A phrase match. */
    public const float QA_MATCH_CONFIDENCE = 0.99;

    /** Label for a retrieved chunk whose document has no name or URL. */
    private const string UNKNOWN_DOCUMENT = 'Unknown';

    public function __construct(
        private readonly KnowledgeBase $knowledgeBase,
        private readonly GuardrailService $guardrails,
        private readonly GuardrailRecorder $guardrailRecorder,
    ) {}

    public function enrich(ChatContext $context): ChatContext
    {
        // A guard-blocked turn never reaches the LLM, so the (costly)
        // retrieval is skipped entirely.
        if ($context->guardBlocked) {
            return $context;
        }

        $agent = $context->requestData->agent;

        // Q&A matches on the stored message, not the rewritten query.
        $qaMatch = $this->findQaMatch($agent, $context->payload->message);

        if ($qaMatch !== null) {
            $context->setQaMatch($qaMatch);
            $context->setConfidenceScore($qaMatch->confidenceScore);

            return $context;
        }

        $sources = $this->withoutInjectedSources($context, $this->retrieveSources($agent, (string) $context->payload->message_for_llm));
        $context->setSources($sources);

        if ($sources->isEmpty()) {
            return $context;
        }

        return $this->withReferenceMaterial($context, $sources);
    }

    /** Strict phrase match: the exact phrase, in order. */
    private function findQaMatch(Agent $agent, string $message): ?QaMatchData
    {
        $match = AgentQuestionAnswer::query()->phraseSearch($agent->id, $message)[0] ?? null;

        if ($match === null) {
            return null;
        }

        return new QaMatchData(
            id: (int) $match->id,
            questions: $match->questions,
            answer: (string) $match->answer,
            confidenceScore: self::QA_MATCH_CONFIDENCE,
        );
    }

    /**
     * Always search: the reranker's relevance threshold drops chunks that do
     * not really match, so greetings come back empty without a separate gate.
     *
     * @return Collection<int, RetrievedSourceData>
     */
    private function retrieveSources(Agent $agent, string $query): Collection
    {
        $chunks = collect($this->knowledgeBase->search($agent, $query));

        if ($chunks->isEmpty()) {
            return collect();
        }

        $documents = $this->documentsBehind($chunks);

        return $chunks->map(fn (RetrievedChunkData $chunk): RetrievedSourceData => new RetrievedSourceData(
            id: $chunk->id,
            external_id: $chunk->external_id,
            content: $chunk->chunk,
            confidence_score: $chunk->confidence_score,
            metadata: [
                'filename' => $this->documentName(($documents[$chunk->chunkable_type] ?? null)?->get($chunk->chunkable_id)),
                'document_id' => $chunk->id,
                'chunkable_type' => $chunk->chunkable_type,
                'chunkable_id' => $chunk->chunkable_id,
            ],
            images: $chunk->images(),
        ));
    }

    /**
     * The documents the chunks were cut from, loaded once per type and keyed
     * by morph alias, then id. `chunks.chunkable_type` stores the morph alias,
     * so it is resolved back to the model class before querying.
     *
     * @param  Collection<int, RetrievedChunkData>  $chunks
     * @return array<string, EloquentCollection<array-key, Model>>
     */
    private function documentsBehind(Collection $chunks): array
    {
        $documents = [];

        foreach ($chunks->groupBy('chunkable_type') as $type => $group) {
            /** @var class-string<Model> $class */
            $class = Relation::getMorphedModel((string) $type) ?? $type;

            $documents[(string) $type] = $class::query()
                ->whereIn('id', $group->pluck('chunkable_id')->unique())
                ->get()
                ->keyBy('id');
        }

        return $documents;
    }

    /** A document's file name or URL, as the sources list shows it. */
    private function documentName(?Model $document): string
    {
        return $document->filename ?? ($document->url ?? self::UNKNOWN_DOCUMENT);
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
     * material. Only that message changes; the payload is cloned, so a field
     * added later can never be dropped by a hand-written rebuild.
     *
     * @param  Collection<int, RetrievedSourceData>  $sources
     */
    private function withReferenceMaterial(ChatContext $context, Collection $sources): ChatContext
    {
        $confidence = $this->averageConfidence($sources);

        $enriched = new ChatContext(
            requestData: $context->requestData,
            payload: $context->payload->withMessageForLlm(
                $this->referenceMaterial((string) $context->payload->message_for_llm, $sources),
            ),
            llmMessages: $context->llmMessages,
            userMessageId: $context->userMessageId,
            assistantMessageId: $context->assistantMessageId,
            queryLanguage: $context->queryLanguage,
            startTime: $context->getStartTime(),
        );

        $enriched->setSources($sources);
        $enriched->setConfidenceScore($confidence);

        return $enriched;
    }

    /**
     * @param  Collection<int, RetrievedSourceData>  $sources
     */
    private function averageConfidence(Collection $sources): ?float
    {
        $scores = $sources->pluck('confidence_score')->filter(static fn (?float $score): bool => $score !== null);

        return $scores->isEmpty() ? null : round($scores->avg(), 4);
    }

    /**
     * The visitor's question followed by the retrieved chunks, numbered, with
     * a signed link for every image so the model can show them.
     *
     * @param  Collection<int, RetrievedSourceData>  $sources
     */
    private function referenceMaterial(string $question, Collection $sources): string
    {
        return prompt('knowledge/reference-material', [
            'question' => $question,
            'hasImages' => $sources->contains(static fn (RetrievedSourceData $source): bool => $source->images !== []),
            'items' => $sources->map(fn (RetrievedSourceData $source): array => [
                'content' => $source->content,
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
                'description' => str_replace([']', '[', '(', ')', "\n", "\r"], ' ', (string) $description),
                // Always the main domain (not the agent subdomain). The path
                // carries an HMAC over (chunk, index), so the public image route
                // only serves links the server minted.
                'url' => rtrim((string) config('app.url'), '/').ChunkImageLink::path((string) $source->external_id, $index),
            ];
        }

        return $links;
    }
}
