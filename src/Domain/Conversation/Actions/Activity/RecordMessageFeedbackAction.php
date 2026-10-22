<?php

namespace Domain\Conversation\Actions\Activity;

use Domain\Analytics\Events\FeedbackGiven;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Enums\MessageFeedback;
use Domain\Conversation\Models\Message;
use Domain\Conversation\QueryBuilders\ConversationQueryBuilder;
use Domain\Conversation\Services\Memory\ChatMemory;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Records a thumbs-up / thumbs-down on an assistant reply (or clears it with
 * null), in the live memory and in the chat log, and tells analytics when the
 * rating changed.
 *
 * The database is written even when the conversation is no longer in the
 * live memory (expired, evicted, or after a cache restart), because the
 * dashboard reads the rating from there. Only messages of the conversation in
 * scope can be rated.
 */
class RecordMessageFeedbackAction
{
    public function __construct(
        private readonly ChatMemory $memory,
    ) {}

    public function execute(ChatScopeData $scope, string $messageId, ?MessageFeedback $feedback): void
    {
        $conversationId = $scope->conversationId
            ?? throw new InvalidArgumentException('A conversation id is required to rate a message.');
        $value = $feedback?->value;

        $this->updateLiveCopy($scope, $conversationId, $messageId, $value);
        $this->updateStoredCopy($scope, $conversationId, $messageId, $value);
    }

    /**
     * When the message is still in the live memory: update it there and in the
     * database, and announce a changed rating with the model that answered.
     */
    private function updateLiveCopy(ChatScopeData $scope, string $conversationId, string $messageId, ?string $value): void
    {
        $memory = $this->memory->withScope($scope);
        // The cached copy, not the client view: saving the client view back
        // would strip every message's LLM-facing text from the memory.
        $messages = $memory->cachedMessages();

        foreach ($messages as $index => $message) {
            if ($message->id !== $messageId) {
                continue;
            }

            $messages[$index] = $message->withFeedback($value);

            Message::query()->where('external_id', $messageId)->update(['feedback' => $value]);

            if ($message->feedback !== $value) {
                $this->announce($conversationId, $messageId, $value, $message->llm_id);
            }
        }

        $memory->saveMessages($messages);
    }

    /**
     * The database is the source of truth the dashboard reads. A no-op when
     * the live path already wrote the same value; analytics upserts, so a
     * rating is never counted twice. The model name lives only in the live
     * memory, so a rating recorded here carries none.
     */
    private function updateStoredCopy(ChatScopeData $scope, string $conversationId, string $messageId, ?string $value): void
    {
        $message = Message::query()
            ->where('external_id', $messageId)
            ->whereHas('conversation', static function (ConversationQueryBuilder $query) use ($scope, $conversationId): void {
                $query->inSession($scope->sessionId, $scope->agent->id)->where('external_id', $conversationId);
            })
            ->first(['id', 'feedback']);

        if ($message === null || $message->feedback?->value === $value) {
            return;
        }

        $message->update(['feedback' => $value]);

        $this->announce($conversationId, $messageId, $value, null);
    }

    /**
     * Fire FeedbackGiven with the analytics dimensions of the message. One
     * small join; ratings are rare.
     */
    private function announce(string $conversationExternalId, string $messageExternalId, ?string $value, ?int $llmId): void
    {
        $dimensions = DB::table('chat_messages as m')
            ->join('chat_conversations as c', 'c.id', '=', 'm.chat_conversation_id')
            ->join('chat_sessions as s', 's.id', '=', 'c.chat_session_id')
            ->join('agents as ag', 'ag.id', '=', 's.agent_id')
            ->leftJoin('countries as co', 'co.id', '=', 's.country_id')
            ->where('m.external_id', $messageExternalId)
            ->select('s.agent_id', 'ag.workspace_id', 's.chatter_id', 'co.code as country_code')
            ->first();

        if ($dimensions === null) {
            return;
        }

        FeedbackGiven::dispatch(
            (int) $dimensions->agent_id,
            (int) $dimensions->workspace_id,
            $dimensions->chatter_id !== null ? (int) $dimensions->chatter_id : null,
            $conversationExternalId,
            $messageExternalId,
            $value,
            $llmId,
            $dimensions->country_code,
            now()->toDateTimeImmutable(),
        );
    }
}
