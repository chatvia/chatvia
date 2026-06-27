<?php

namespace Domain\Conversation\Services;

use Carbon\Carbon;
use Domain\Agent\Models\Agent;
use Domain\Billing\Jobs\UpdateUserCreditsJob;
use Domain\Conversation\Data\Messages\ChatInputPayload;
use Domain\Conversation\Data\Messages\ChatOutputPayload;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\ToolApprovalCheckpoint;
use Domain\Conversation\Services\Data\StreamRequestData;
use Domain\Conversation\Services\Data\StreamRequestMessageData;
use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Guardrail\Services\GuardrailRecorder;
use Domain\Guardrail\Services\GuardrailService;
use Domain\Knowledge\Ingestion\Services\Search\KnowledgeBase;
use Domain\Knowledge\Source\QA\Models\AgentQuestionAnswer;
use Domain\Llm\Models\Llm;
use Domain\Llm\Operations\Query\QueryRewriter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Support\Http\ChunkImageLink;

/**
 * Chat turn lifecycle: build the context, enrich with knowledge / Q&A, prepare
 * the LLM message stack, persist the assistant's output. The agentic loop
 * (tool calls + iterations + approval-gating) lives in ToolLoopRunner; LlmSource
 * orchestrates the SSE envelope around the loop.
 *
 * MessageProcessor's surface was folded in (H11). The private helpers below
 * (`prepareSystemMessage`, `searchQA`, `searchKnowledgeBaseFor`,
 * `buildContextualUserMessage`, `enrichSourcesWithMetadata`) were its public
 * methods; nothing else consumed them, so removing the indirection saves one
 * class without changing behaviour.
 */
readonly class ChatService
{
    public function __construct(
        private ChatMemory $memory,
        private KnowledgeBase $knowledgeBase,
        private GuardrailService $guardrails,
        private GuardrailRecorder $guardrailRecorder,
    ) {}

    public function initializeChatContext(StreamRequestData $data): ChatContext
    {
        $this
            ->memory
            ->withContext($data->agent, $data->externalSessionId, $data->conversationId);

        ['lang' => $lang, 'query' => $query] = app(QueryRewriter::class)->rewrite($data->message->message);

        $userMessageId = Str::uuid()->toString();
        $assistantMessageId = Str::uuid()->toString();

        $attachments = $data->message->attachments;

        // Input guardrails (PII / injection / topic / toxicity). PII redaction
        // applies to what the LLM sees; a genuine injection / off-topic / toxic
        // message sets $guard->blocked, and the turn routes to GuardBlockSource
        // (no LLM call, no credit) — see ChatStreamService.
        $guard = $this->guardrails->inspectInput($data->agent, $data->message->message);

        // The guarded text has PII masked (identical to the original when there's
        // no PII). It becomes BOTH what we persist/display AND — when PII was
        // found — what the LLM sees, so a raw identifier never lands in the
        // stored record, the chat log, the replayed history, or the model
        // context. This honours the "mask … in the message" guarantee. Without
        // PII we still send the rewritten query for better retrieval.
        //
        // PDFs are delivered to the model as `document_url` chunks by
        // MistralPdfSource, so the visible user content stays clean.
        $storedMessage = $guard->text;
        $piiRedacted = $storedMessage !== $data->message->message;
        $messageForLlm = $piiRedacted ? $storedMessage : $query;

        $payload = ChatInputPayload::from([
            'external_session_id' => $data->externalSessionId,
            'external_conversation_id' => $data->conversationId,
            'external_message_id' => $userMessageId,
            'model' => $data->message->model->external_name,
            'message' => $storedMessage,
            'sent_at' => $data->message->createdAt ? Carbon::parse($data->message->createdAt) : now(),
            'updated_message_info' => [],
            'message_for_llm' => $messageForLlm,
            'user_id' => $data->chatterUserId,
            'language' => $lang,
            'ip_address' => $data->ipAddress,
            'country' => $data->country,
            'user_agent' => $data->userAgent,
            'referrer_url' => $data->referrerUrl,
            'page_url' => $data->pageUrl,
            'locale' => $data->locale,
            'source' => $data->source,
            'attachments' => $attachments,
        ]);

        $llmMessages = $this->memory->getLlmMessages();

        // Add user message if not the first conversation
        if ($data->saveUserMessageImmediately || count($llmMessages) > 1) {
            $this
                ->memory
                ->withContext($data->agent, $data->externalSessionId, $data->conversationId)
                ->addMessageViaPayload($payload);
        } else {
            // The user message was already persisted (typically by
            // CreateConversationAction for new conversations). Reuse its
            // existing id instead of the throwaway UUID we just generated,
            // otherwise the SSE `start` event tells the frontend a UUID that
            // doesn't exist anywhere — breaking regenerate/edit later when
            // the client sends that stale id back to the server.
            $cachedMessages = $this->memory->getMessagesForConversation();
            // CreateConversationAction persists the first user message already
            // masked, so match on the masked text — not the raw original.
            $existingUserMessage = collect($cachedMessages)
                ->last(fn (array $message): bool => $message['role'] === 'user' && $message['content'] === $storedMessage);

            if ($existingUserMessage) {
                $userMessageId = $existingUserMessage['id'];
                $payload->external_message_id = $userMessageId;
            }
        }

        $context = new ChatContext(
            requestData: $data,
            payload: $payload,
            llmMessages: $llmMessages,
            userMessageId: $userMessageId,
            assistantMessageId: $assistantMessageId,
            queryLanguage: $lang,
        );

        // Carry the block decision to ChatStreamService, and log every input
        // verdict (PII redaction / injection block / topic / toxicity). Record
        // the masked text, never the raw message — the audit snippet must not
        // itself become a PII sink.
        $context->guardBlocked = $guard->blocked;
        $context->guardRefusal = $guard->refusalMessage;
        $this->guardrailRecorder->record($context, $storedMessage, $guard);

        return $context;
    }

    public function prepareLlmMessages(ChatContext $context): array
    {
        return $this->prepareLlmMessagesFor(
            $context->requestData,
            $context->payload,
            $context->llmMessages,
        );
    }

    /**
     * Rebuild a ChatContext from a paused tool-approval checkpoint so the tool
     * loop can resume. Unlike initializeChatContext this creates no new
     * messages and runs no query rewriting: the LLM message history is restored
     * verbatim from the snapshot, and the existing assistant message id is
     * reused. The streamed buffer starts empty so the persisted message is the
     * post-approval answer — the pre-approval preface only ever shows live and
     * on the paused-state reload (sourced from the checkpoint), matching how
     * normal tool-call prefaces are dropped from the saved message.
     */
    public function contextForResume(ToolApprovalCheckpoint $checkpoint): ChatContext
    {
        $ctx = $checkpoint->context;
        $model = Llm::findOrFail($ctx['model_id']);

        $requestData = new StreamRequestData(
            agent: $checkpoint->agent,
            message: new StreamRequestMessageData(
                model: $model,
                message: (string) ($ctx['user_message'] ?? ''),
            ),
            externalSessionId: (string) ($ctx['external_session_id'] ?? ''),
            conversationId: $checkpoint->conversation_external_id,
            chatterUserId: $ctx['chatter_user_id'] ?? null,
            chatterName: $ctx['chatter_name'] ?? null,
            actorUserId: $ctx['actor_user_id'] ?? null,
            actorName: $ctx['actor_name'] ?? null,
            streamSources: (bool) ($ctx['stream_sources'] ?? false),
            source: $ctx['source'] ?? null,
            requestId: $checkpoint->request_id,
        );

        $payload = ChatInputPayload::from([
            'external_session_id' => (string) ($ctx['external_session_id'] ?? ''),
            'external_conversation_id' => $checkpoint->conversation_external_id,
            'external_message_id' => $checkpoint->assistant_message_id,
            'model' => $model->external_name,
            'message' => (string) ($ctx['user_message'] ?? ''),
            'sent_at' => now(),
            'language' => $ctx['query_language'] ?? null,
            'source' => $ctx['source'] ?? null,
        ]);

        return new ChatContext(
            requestData: $requestData,
            payload: $payload,
            llmMessages: $checkpoint->messages_snapshot,
            userMessageId: null,
            assistantMessageId: $checkpoint->assistant_message_id,
            queryLanguage: $ctx['query_language'] ?? null,
        );
    }

    /**
     * Update context with knowledge base sources and contextual message.
     * First checks for high-confidence Q&A match before searching chunks.
     */
    public function enrichContextWithKnowledge(ChatContext $context): ChatContext
    {
        // A guard-blocked turn never reaches the LLM, so skip the (costly) RAG
        // retrieval entirely — it routes to GuardBlockSource.
        if ($context->guardBlocked) {
            return $context;
        }

        // First, check for high-confidence Q&A match using ORIGINAL message (not rewritten)
        $qaMatch = $this->searchQA(
            $context->requestData->agent->id,
            $context->payload->message  // Use original message for Q&A matching
        );

        if ($qaMatch) {
            // Q&A match found - skip chunk search
            $context->setQAMatch($qaMatch);
            $context->setConfidenceScore($qaMatch->confidence_score);

            return $context;
        }

        // No Q&A match - search knowledge base chunks
        $sources = $this->searchKnowledgeBaseFor(
            $context->requestData->agent,
            $context->payload,
        );

        // RAG-source injection guard: drop any retrieved chunk whose content
        // carries injection markers before it is concatenated into the prompt.
        $sources = $this->guardSources($context, $sources);

        $context->setSources($sources);

        // If we don't have sources, return the original context
        if ($sources->isEmpty()) {
            return $context;
        }

        // Calculate average confidence score from sources
        $avgConfidence = $this->calculateAverageConfidence($sources);
        $context->setConfidenceScore($avgConfidence);

        $contextualMessage = $this->buildContextualUserMessage(
            $context->payload,
            $sources
        );

        // Only message_for_llm changes when we enrich with retrieved context;
        // clone the payload so a newly-added field can never be silently dropped
        // by a hand-maintained field-by-field rebuild.
        $enrichedPayload = $context->payload->withMessageForLlm($contextualMessage);

        $enrichedContext = new ChatContext(
            requestData: $context->requestData,
            payload: $enrichedPayload,
            llmMessages: $context->llmMessages,
            userMessageId: $context->userMessageId,
            assistantMessageId: $context->assistantMessageId,
            queryLanguage: $context->queryLanguage,
            startTime: $context->getStartTime(),
        );

        $enrichedContext->setSources($sources);
        $enrichedContext->setConfidenceScore($avgConfidence);

        return $enrichedContext;
    }

    /**
     * Drop retrieved RAG chunks whose content trips the source injection guard,
     * logging each one. A neutralised source never reaches the model's prompt.
     *
     * @param  Collection<int, array<string, mixed>>  $sources
     * @return Collection<int, array<string, mixed>>
     */
    private function guardSources(ChatContext $context, Collection $sources): Collection
    {
        $agent = $context->requestData->agent;

        if (! $this->guardrails->enabledFor($agent)) {
            return $sources;
        }

        return $sources->reject(function ($chunk) use ($agent, $context): bool {
            $content = (string) ($chunk['content'] ?? '');
            $scan = $this->guardrails->scanSource($agent, $content);

            if ($scan->triggered()) {
                $this->guardrailRecorder->record($context, $content, $scan);

                return true;
            }

            return false;
        })->values();
    }

    /**
     * Persist an assistant message WITHOUT billing — used by GuardBlockSource so a
     * refused turn is recorded in history but never charges a message credit.
     */
    public function persistAssistantMessage(ChatContext $context): void
    {
        $this->saveAssistantMessage($context);
    }

    public function handleChatCompletion(ChatContext $context): void
    {
        // Update user credits: the model's base cost plus one message per PDF
        // page in this conversation. PDFs are re-sent to Mistral on every turn
        // (see ChatStreamService / MistralPdfSource, gated on the same
        // pdfAttachments relation), so the page charge recurs each turn the
        // document is in context — not just the upload turn.
        UpdateUserCreditsJob::dispatch(
            userId: $context->requestData->agent->user_id,
            modelId: $context->requestData->message->model->id,
            additionalMessages: $this->pdfPageCredits($context),
        );

        // Always persist messages (including playground)
        $this->saveAssistantMessage($context);

        // Titling is no longer triggered here — the client calls the synchronous
        // `conversations.title` endpoint after the first turn (no queue).

        if ($context->requestData->streamSources) {
            $this->memory->storeSourcesForMessage(
                $context->assistantMessageId,
                $context->sources->toArray()
            );
        }
    }

    /**
     * Extra message credits for this turn: the summed page count of every PDF
     * attached to the conversation. Mirrors the pdfAttachments condition that
     * routes the turn to Mistral, so the charge applies exactly when the
     * document was actually processed. Returns 0 for plain text conversations.
     */
    private function pdfPageCredits(ChatContext $context): int
    {
        $conversationId = $context->requestData->conversationId;

        if (! $conversationId) {
            return 0;
        }

        return (int) (Conversation::query()
            ->where('external_id', $conversationId)
            ->first()
            ?->pdfAttachments()
            ->sum('pages') ?? 0);
    }

    private function saveAssistantMessage(ChatContext $context): void
    {
        $this
            ->memory
            ->withContext($context->requestData->agent, $context->requestData->externalSessionId, $context->requestData->conversationId)
            ->addMessageViaPayload(
                new ChatOutputPayload(
                    external_session_id: $context->requestData->externalSessionId,
                    external_conversation_id: $context->requestData->conversationId,
                    external_message_id: $context->assistantMessageId,
                    message: $context->streamedMessage,
                    model: $context->requestData->message->model->external_name,
                    sent_at: now(),
                    generation_id: $context->generationId,
                    prompt_tokens: $context->usage['prompt_tokens'] ?? null,
                    completion_tokens: $context->usage['completion_tokens'] ?? null,
                    response_time_ms: $context->getResponseTimeMs(),
                    language: $context->queryLanguage,
                    confidence_score: $context->confidenceScore,
                    is_response_from_qa: $context->hasQAMatch(),
                    agent_question_answer_id: $context->qaMatch?->id,
                    source: $context->requestData->source,
                    has_llm_found_answer: $this->didFindAnswer($context),
                    artifacts: $context->artifacts,
                    tool_calls: $context->toolCallNames,
                )
            );
    }

    /**
     * Decide whether this turn produced an answer, using deterministic
     * retrieval signals — never the LLM's self-report.
     */
    private function didFindAnswer(ChatContext $context): bool
    {
        if ($context->hasQAMatch()) {
            return true;
        }

        if ($context->sources->isEmpty()) {
            return false;
        }

        return $context->confidenceScore !== null
            && $context->confidenceScore >= 0.65;
    }

    /**
     * Calculate average confidence score from knowledge base sources
     */
    private function calculateAverageConfidence(Collection $sources): ?float
    {
        if ($sources->isEmpty()) {
            return null;
        }

        $confidenceScores = $sources
            ->pluck('confidence_score')
            ->filter(fn ($score): bool => $score !== null);

        if ($confidenceScores->isEmpty()) {
            return null;
        }

        return round($confidenceScores->avg(), 4);
    }

    // ===========================================================
    // ===  Folded-in from MessageProcessor (H11 consolidation) ===
    // ===========================================================

    private function prepareLlmMessagesFor(StreamRequestData $data, ChatInputPayload $payload, array $messages): array
    {
        $systemPrompt = $this->buildSystemPrompt($data);

        // Get the current user message content (may include retrieved context)
        $userMessageContent = $payload->message_for_llm;

        // Check if the last message is already a user message
        $lastMessage = end($messages);
        $hasUserMessage = $lastMessage && $lastMessage['role'] === 'user';

        if ($hasUserMessage) {
            // Replace the last user message with the enriched one
            array_pop($messages);
        }

        // Add the current user message (either new or updated with context)
        $userMessage = [
            'role' => 'user',
            'content' => $userMessageContent,
        ];

        return array_merge([$systemPrompt], $messages, [$userMessage]);
    }

    /**
     * Strict-phrase Q&A match. Exact phrase, word order matters.
     */
    private function searchQA(int $agentId, string $query): ?object
    {
        $results = AgentQuestionAnswer::query()->phraseSearch($agentId, $query);

        if (empty($results)) {
            return null;
        }

        $qa = $results[0];
        $qa->confidence_score = 0.99;

        return $qa;
    }

    private function searchKnowledgeBaseFor(Agent $agent, ChatInputPayload $payload): Collection
    {
        // Always search. The reranker's relevance_threshold drops chunks that
        // don't actually match — greetings/acknowledgments produce an empty
        // result set without needing a separate LLM gate to short-circuit.
        $searchResults = $this->knowledgeBase->search($agent, $payload->message_for_llm);

        return $this->enrichSourcesWithMetadata($searchResults);
    }

    private function buildSystemPrompt(StreamRequestData $data): array
    {
        $systemPrompt = $data->agent->resolveSystemInstructions();

        $systemPrompt .= prompt('knowledge/grounding');

        // Protected agents always know who they're talking to: the chatter had to
        // sign in to reach them. Tell the model their name so it can address them
        // personally. Public agents serve anonymous visitors — skip it there even
        // if a chatter happens to be signed in.
        if (! $data->agent->is_public && filled($data->chatterName)) {
            $systemPrompt .= "\n\n".prompt('chatter/identity', ['name' => $data->chatterName]);
        }

        $business = array_filter($data->agent->settings('business')->toArray(), static fn ($v): bool => filled($v));
        if ($business !== []) {
            $systemPrompt .= "\n\n".prompt('business/profile', [
                'email' => $business['email'] ?? null,
                'phone' => $business['phone'] ?? null,
                'website' => $business['website'] ?? null,
                'address' => $business['address'] ?? null,
                'opening_hours' => $business['opening_hours'] ?? null,
            ]);
        }

        // Forms are NOT listed in the system prompt. The agent discovers them
        // dynamically through the Forms MCP server (find-forms → render-form), so a
        // form only enters the model's context once it actively searches by the
        // visitor's intent — it can't surface a form on a general question. The
        // web-only channel gate lives in those tools (FindFormsTool/RenderFormTool).

        return [
            'role' => 'system',
            'content' => $systemPrompt,
        ];
    }

    private function buildContextualUserMessage(ChatInputPayload $payload, Collection $sources): string
    {
        if ($sources->isEmpty()) {
            return $payload->message_for_llm;
        }

        $message = Str::of("### User Question:\n")
            ->append($payload->message_for_llm)
            ->append("\n\n### Retrieved Context\n");

        // Check if any sources have images
        $hasImages = $sources->contains(fn ($chunk): bool => ! empty($chunk['images']));

        if ($hasImages) {
            $message = $message->append("\n⚠️ CRITICAL INSTRUCTION: This context includes images. You MUST include relevant images in your response using markdown format: ![description](url)\n\n");
        }

        $message = $message->append($sources->map(function ($chunk, $i) {
            $text = "Context {$i}:\n".$chunk['content']."\n";

            if (! empty($chunk['images'])) {
                $text .= "\n📷 IMAGES FOR THIS CONTEXT (REQUIRED - Include in response):\n";

                $imageIndex = 0;
                foreach ($chunk['images'] as $description) {
                    // Always use main domain URL instead of subdomain-based route().
                    // The path carries a constant-time HMAC over (chunk id, index)
                    // so the public chunk-image route only serves links the server
                    // minted — closing the cross-tenant IDOR on guessable chunk ids.
                    $path = ChunkImageLink::path($chunk['external_id'], $imageIndex);
                    $imageUrl = rtrim(config('app.url'), '/').$path;
                    // The description is LLM-generated alt-text captured from an
                    // ingested document image (attacker-influenceable). Strip the
                    // markdown link delimiters so it can't break out of the
                    // alt-text and inject an extra attacker-controlled image URL.
                    $safeDescription = str_replace([']', '[', '(', ')', "\n", "\r"], ' ', (string) $description);
                    $text .= "- Image {$imageIndex}: {$safeDescription}\n";
                    $text .= "  Markdown: ![{$safeDescription}]({$imageUrl})\n";
                    $imageIndex++;
                }
                $text .= "\n";
            }

            return $text;
        })->implode("\n"));

        return $message->toString();
    }

    private function enrichSourcesWithMetadata(array $searchResults): Collection
    {
        if (empty($searchResults)) {
            return collect();
        }

        // Get unique chunkable IDs and their types
        $chunkableItems = collect($searchResults)
            ->groupBy('chunkable_type')
            ->map(function ($results, string $type) {
                $ids = $results->pluck('chunkable_id')->unique();

                // chunks.chunkable_type stores the morph alias (e.g. "source_file");
                // resolve it back to the model class before querying.
                /** @var class-string<Model> $class */
                $class = Relation::getMorphedModel($type) ?? $type;

                return $class::whereIn('id', $ids)->get()->keyBy('id');
            });

        return collect($searchResults)->map(function ($result) use ($chunkableItems) {
            $chunkable = $chunkableItems[$result->chunkable_type]?->get($result->chunkable_id);
            $metadata = json_decode($result->metadata, true);

            return [
                'id' => $result->id,
                'external_id' => $result->external_id,
                'content' => $result->chunk,
                'confidence_score' => $result->confidence_score ?? null,
                'metadata' => [
                    'filename' => $chunkable->filename ?? ($chunkable->url ?? 'Unknown'),
                    'document_id' => $result->id,
                    'chunkable_type' => $result->chunkable_type,
                    'chunkable_id' => $result->chunkable_id,
                ],
                'images' => $metadata['images'] ?? [],
            ];
        });
    }
}
