<?php

namespace Domain\Conversation\Actions\Messages;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Messages\AssistantTurnData;
use Domain\Conversation\Data\Messages\MessageSourceData;
use Domain\Conversation\Data\Messages\ReplyUsageData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Jobs\GenerateConversationTitleJob;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\MessageSource;
use Domain\Conversation\Services\ChatLog\ChatLogWriter;
use Domain\Llm\Exceptions\LlmProviderUnavailableException;
use Domain\Llm\Models\Llm;
use Domain\Llm\Operations\Pricing\CostCalculator;
use Domain\Llm\Services\LlmProviders;
use Illuminate\Support\Facades\DB;

/**
 * Writes one assistant reply to the chat log. It first settles the reply's
 * token usage (from the stream, or from the provider when the stream carried
 * none), then in one transaction stores the message under its session and
 * conversation, with the knowledge it was given. Once committed it announces the message to analytics and, on
 * the first reply, queues the conversation's title.
 */
class StoreAssistantMessageAction
{
    public function __construct(
        private readonly LlmProviders $providers,
        private readonly ChatLogWriter $chatLog,
    ) {}

    public function execute(Agent $agent, AssistantTurnData $turn): Message
    {
        // May call the provider over HTTP, so it runs before the transaction:
        // no row lock is held while waiting on the network.
        $usage = $this->resolveUsage($turn);

        $message = DB::transaction(function () use ($agent, $turn, $usage): Message {
            $session = $this->chatLog->session($agent, $turn->external_session_id);
            $conversation = $this->chatLog->conversation($session, $turn->external_conversation_id, $turn->source);

            $message = Message::create($this->attributes($turn, $conversation, $usage));

            $this->storeSources($message, $turn);

            return $message->setRelation('conversation', $conversation->setRelation('session', $session));
        });

        $conversation = $message->conversation;

        $this->chatLog->announce($agent, $conversation->session, $message, $turn);
        $this->queueTitle($agent, $conversation, $turn);

        return $message;
    }

    /**
     * The reply's usage, best source first:
     *  1. inline usage from the stream — the provider-agnostic billed count
     *     (it carries no cost, so cost stays null);
     *  2. a post-hoc lookup by generation id — tokens and dollar cost, for
     *     providers that support it (one extra HTTP round trip);
     *  3. nothing — the token log then falls back to an estimate.
     */
    private function resolveUsage(AssistantTurnData $turn): ReplyUsageData
    {
        if ($turn->prompt_tokens !== null || $turn->completion_tokens !== null) {
            return new ReplyUsageData($turn->prompt_tokens, $turn->completion_tokens);
        }

        $lookup = $turn->generation_id ? $this->generationLookup($turn->model, $turn->generation_id) : null;

        $costInDollars = $lookup['total_cost'] ?? null;

        return new ReplyUsageData(
            promptTokens: $lookup['tokens_prompt'] ?? null,
            completionTokens: $lookup['tokens_completion'] ?? null,
            costInMicroCents: $costInDollars !== null ? CostCalculator::dollarsToMicroCents((float) $costInDollars) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(AssistantTurnData $turn, Conversation $conversation, ReplyUsageData $usage): array
    {
        return [
            'external_id' => $turn->external_message_id,
            'workspace_id' => $conversation->workspace_id,
            'chat_conversation_id' => $conversation->id,
            'content' => $turn->message,
            'role' => MessageRole::Assistant,
            'llm_id' => $this->chatLog->llmId($turn->model),
            'response_time_ms' => $turn->response_time_ms,
            'generation_id' => $turn->generation_id,
            'prompt_tokens' => $usage->promptTokens,
            'completion_tokens' => $usage->completionTokens,
            'cost_in_micro_cents' => $usage->costInMicroCents,
            'confidence_score' => $turn->confidence_score,
            'is_response_from_qa' => $turn->is_response_from_qa,
            'agent_question_answer_id' => $turn->agent_question_answer_id,
            'source' => $turn->source,
            'has_llm_found_answer' => $turn->has_llm_found_answer,
            'metadata' => $turn->storedMetadata(),
            'created_at' => $turn->sent_at,
            'updated_at' => $turn->sent_at,
        ];
    }

    /** The knowledge the answer was given, in its order, under the message's workspace. */
    private function storeSources(Message $message, AssistantTurnData $turn): void
    {
        if ($turn->sources === []) {
            return;
        }

        MessageSource::query()->insert(array_map(static fn (MessageSourceData $source): array => [
            'workspace_id' => $message->workspace_id,
            'chat_message_id' => $message->id,
            'rank' => $source->rank,
            'chunk_id' => $source->chunk_id,
            'sourceable_type' => $source->sourceable_type,
            'sourceable_id' => $source->sourceable_id,
            'score' => $source->score,
            'label' => mb_substr($source->label, 0, 500),
            'url' => $source->url !== null ? mb_substr($source->url, 0, 2048) : null,
            'page' => $source->page,
        ], $turn->sources));
    }

    /**
     * Name the conversation once its first reply lands, on every surface
     * except Hosted Chat — that client titles itself right after the first
     * turn, so queueing here too would mean a second LLM call and a title
     * flicker. needsTitle() keeps this to one run and never renames a
     * conversation that already has a name.
     */
    private function queueTitle(Agent $agent, Conversation $conversation, AssistantTurnData $turn): void
    {
        if ($conversation->source === ConversationSource::HostedChat || ! $conversation->needsTitle()) {
            return;
        }

        GenerateConversationTitleJob::dispatch($agent, $turn->external_session_id, $turn->external_conversation_id);
    }

    /**
     * The provider's record of one generation (tokens and cost), when the
     * reply's model has a provider that keeps one.
     *
     * @return array<string, mixed>|null
     */
    private function generationLookup(string $model, string $generationId): ?array
    {
        $llm = Llm::catalog()->firstWhere('external_name', $model);

        if (! $llm instanceof Llm) {
            return null;
        }

        try {
            $provider = $this->providers->for($llm);
        } catch (LlmProviderUnavailableException) {
            return null;
        }

        return $provider->supportsCostTracking() ? $provider->getGenerationCost($generationId) : null;
    }
}
