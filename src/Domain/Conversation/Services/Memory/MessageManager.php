<?php

namespace Domain\Conversation\Services\Memory;

use Domain\Analytics\Events\FeedbackGiven;
use Domain\Conversation\Data\Memory\MessageData;
use Domain\Conversation\Data\Messages\ChatInputPayload;
use Domain\Conversation\Data\Messages\ChatOutputPayload;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Manages chat messages within conversations.
 */
class MessageManager
{
    public function __construct(
        private readonly ChatCache $cache,
    ) {}

    public function addMessage(
        string $conversationId,
        string $role,
        string $message,
        ?string $messageId = null,
        ?string $model = null,
        string $type = 'text',
        array $append = []
    ): string {
        $messageId = $messageId ?? Str::uuid()->toString();

        $messageData = MessageData::from([
            'id' => $messageId,
            'role' => $role,
            'content' => $message,
            'type' => $type,
            'timestamp' => now()->timestamp,
            'read_at' => null,
            'model' => $model,
            ...$append,
        ]);

        $this->saveMessage($conversationId, $messageData->toArray());

        // Mark conversation as visible when first message is added
        $this->markConversationAsVisible($conversationId);

        return $messageId;
    }

    public function addMessageFromPayload(
        string $conversationId,
        ChatInputPayload|ChatOutputPayload $payload
    ): string {
        $isInputMessage = $payload instanceof ChatInputPayload;

        $append = [
            'language' => $payload->language,
        ];

        if ($isInputMessage) {
            $append['message_for_llm'] = $payload->message_for_llm;

            // Add read_at for user messages during handoff
            if ($payload->source === 'handoff') {
                $append['read_at'] = null;
            }
        } else {
            // For output messages, set has_llm_found_answer flag
            $append['has_llm_found_answer'] = $payload->has_llm_found_answer;

            // Persist tool-result artifacts (e.g. ECharts specs) and the
            // list of tool names this turn invoked onto the cached
            // MessageData so /conversations/{id}/messages returns them on
            // reload. Matches what gets written to the DB's
            // `chat_messages.metadata` column by StoreMessageJob.
            $metadataBuf = [];
            if (! empty($payload->artifacts)) {
                $metadataBuf['artifacts'] = $payload->artifacts;
            }
            if (! empty($payload->tool_calls)) {
                $metadataBuf['tool_calls'] = $payload->tool_calls;
            }
            if ($metadataBuf !== []) {
                $append['metadata'] = $metadataBuf;
            }
        }

        $role = $isInputMessage ? 'user' : 'assistant';

        return $this->addMessage(
            conversationId: $conversationId,
            role: $role,
            message: $payload->message,
            messageId: $payload->external_message_id,
            model: $payload->model,
            append: $append
        );
    }

    public function updateMessage(string $conversationId, string $messageId, array $values): void
    {
        $messages = $this->getMessagesForConversation($conversationId);

        foreach ($messages as &$message) {
            if ($message['id'] === $messageId) {
                foreach ($values as $key => $value) {
                    // Capture the prior feedback BEFORE the cache overwrite
                    // below; otherwise $priorFeedback reads back the value we
                    // just wrote, the change check is always false, and the
                    // FeedbackGiven analytics event never fires.
                    $priorFeedback = $key === 'feedback' ? ($message['feedback'] ?? null) : null;

                    $message[$key] = $value;

                    // Handle feedback persistence to database.
                    // $value is 'positive', 'negative', or null (cleared).
                    if ($key === 'feedback') {
                        Message::where('external_id', $message['id'])->update([
                            'feedback' => $value,
                        ]);

                        if ($priorFeedback !== $value) {
                            $this->dispatchFeedbackAnalytics(
                                $conversationId,
                                $message['id'],
                                $value,
                                $message['model'] ?? null,
                            );
                        }
                    }

                    // Handle read_at persistence to database. The cached
                    // message id is the external UUID, so match on external_id
                    // (matching the feedback branch above) — keying on the
                    // integer PK `id` silently matches zero rows.
                    if ($key === 'read_at') {
                        Message::where('external_id', $message['id'])->update([
                            'read_at' => $value,
                        ]);
                    }

                    // Handle has_llm_found_answer persistence to database
                    if ($key === 'has_llm_found_answer') {
                        Message::where('external_id', $message['id'])->update([
                            'has_llm_found_answer' => $value,
                        ]);
                    }
                }
            }
        }

        $this->saveMessages($conversationId, $messages);
    }

    /**
     * Mark messages as read by role (only for handoff/human mode).
     * - For user: mark agent messages as read (NOT assistant - AI messages don't need read receipts)
     * - For admin: mark user messages as read (only those sent during handoff)
     */
    public function markMessagesAsRead(string $conversationId, string $readerType): int
    {
        $messages = $this->getMessagesForConversation($conversationId);
        $now = now();
        $markedCount = 0;

        // Determine which roles to mark as read based on who is reading
        // Only 'agent' role messages (human admin) need read receipts, not 'assistant' (AI)
        $rolesToMark = $readerType === 'user'
            ? ['agent'] // User reads human agent messages only
            : ['user']; // Admin reads user messages

        foreach ($messages as &$message) {
            // Only mark if it has read_at field (handoff messages) and hasn't been read
            if (in_array($message['role'], $rolesToMark) && array_key_exists('read_at', $message) && empty($message['read_at'])) {
                $message['read_at'] = $now->timestamp;
                $markedCount++;

                // Persist to database. Match on external_id — the cached
                // message id is the external UUID, not the integer PK.
                Message::where('external_id', $message['id'])->update([
                    'read_at' => $now,
                ]);
            }
        }

        if ($markedCount > 0) {
            $this->saveMessages($conversationId, $messages);
        }

        return $markedCount;
    }

    public function deleteMessage(string $conversationId, string $messageId): void
    {
        $messages = $this->getMessagesForConversation($conversationId);

        $removedExternalIds = [];
        foreach ($messages as $key => $message) {
            if ($message['id'] === $messageId) {
                // Delete both the message and the previous one (user-assistant pair)
                $removedExternalIds[] = $message['id'];
                if (isset($messages[$key - 1]['id'])) {
                    $removedExternalIds[] = $messages[$key - 1]['id'];
                }
                unset($messages[$key], $messages[$key - 1]);
            }
        }

        // Wipe attachments (and their S3 objects) tied to the removed pair
        // so uploaded files don't linger after the user erases the turn
        // from view. Deleting via Eloquent fires Attachment::booted,
        // which removes the underlying file from the public disk.
        if ($removedExternalIds !== []) {
            $messageIds = Message::query()
                ->whereIn('external_id', $removedExternalIds)
                ->pluck('id');

            if ($messageIds->isNotEmpty()) {
                Attachment::query()
                    ->whereIn('chat_message_id', $messageIds)
                    ->get()
                    ->each(static fn (Attachment $a) => $a->delete());
            }
        }

        $this->saveMessages($conversationId, array_values($messages));
    }

    public function removeMessage(string $conversationId, string $messageId): void
    {
        $messages = $this->getMessagesForConversation($conversationId);

        foreach ($messages as $key => $message) {
            if ($message['id'] === $messageId) {
                unset($messages[$key]);
            }
        }

        $this->saveMessages($conversationId, array_values($messages));
    }

    public function getMessageById(string $conversationId, string $messageId): ?MessageData
    {
        $message = collect($this->getMessagesForConversation($conversationId))
            ->firstWhere('id', $messageId);

        return $message ? MessageData::from($message) : null;
    }

    public function getMessagesForConversation(string $conversationId, bool $includeLlmMessages = false): array
    {
        $cacheKey = $this->cache->generateConversationKey($conversationId);
        $messages = $this->cache->get($cacheKey, [], $conversationId);

        return collect($messages)->map(function (array $message) use ($includeLlmMessages) {
            if (! $includeLlmMessages) {
                unset($message['message_for_llm']);
            }

            return MessageData::from($message)->toArray();
        })->toArray();
    }

    /**
     * Get messages formatted for LLM consumption.
     * Filters out assistant messages where llm_found_answer is false and their preceding user messages.
     */
    public function getLlmMessages(string $conversationId, bool $asText = false, int $limit = 2): array|string
    {
        $cacheKey = $this->cache->generateConversationKey($conversationId);
        $messages = $this->cache->get($cacheKey, [], $conversationId);

        if (empty($messages)) {
            return $asText ? '' : [];
        }

        // Filter out assistant messages where llm_found_answer is false and their preceding user messages
        $messagesToExclude = [];
        $previousUserMessage = null;

        //        foreach ($messages as $index => $message) {
        //            if ($message['role'] === 'user') {
        //                $previousUserMessage = $index;
        //            } elseif ($message['role'] === 'assistant') {
        //                if (($message['has_llm_found_answer'] ?? true) === false) {
        //                    // Exclude this assistant message
        //                    $messagesToExclude[] = $index;
        //                    // Also exclude the previous user message if it exists
        //                    if ($previousUserMessage !== null) {
        //                        $messagesToExclude[] = $previousUserMessage;
        //                    }
        //                }
        //                // Reset previous user message after processing assistant message
        //                $previousUserMessage = null;
        //            }
        //        }

        //        $filteredMessages = collect($messages)
        //            ->reject(function ($message, $index) use ($messagesToExclude) {
        //                return in_array($index, $messagesToExclude);
        //            })
        //            ->values()
        //            ->toArray();

        // Get last N messages for context window
        //        $messages = array_slice($filteredMessages, -$limit);

        $formattedMessages = collect($messages)
            ->map(fn ($message): array => [
                'role' => $message['role'],
                'content' => $message['message_for_llm'] ?? $message['content'],
            ])
            ->toArray();

        if ($asText) {
            return collect($formattedMessages)->pluck('content')->join("\n");
        }

        return $formattedMessages;
    }

    public function getLastMessages(string $conversationId, int $number = 3): array
    {
        $llmMessages = $this->getLlmMessages($conversationId, false, $number);

        return array_reverse(array_slice($llmMessages, -$number));
    }

    public function addFeedback(string $conversationId, string $messageId, ?string $feedback): void
    {
        $this->updateMessage($conversationId, $messageId, ['feedback' => $feedback]);
    }

    /**
     * Resolve the analytics dimensions for the message and fire FeedbackGiven event.
     * Single small join — feedback is rare, so the extra query is cheap.
     */
    private function dispatchFeedbackAnalytics(
        string $conversationExternalId,
        string $messageExternalId,
        ?string $feedback,
        ?string $llmExternalName,
    ): void {
        $context = DB::table('chat_messages as m')
            ->join('chat_conversations as c', 'c.id', '=', 'm.chat_conversation_id')
            ->join('chat_sessions as s', 's.id', '=', 'c.chat_session_id')
            ->join('agents as ag', 'ag.id', '=', 's.agent_id')
            ->leftJoin('countries as co', 'co.id', '=', 's.country_id')
            ->where('m.external_id', $messageExternalId)
            ->select('s.agent_id', 'ag.user_id', 'co.code as country_code')
            ->first();

        if (! $context) {
            return;
        }

        FeedbackGiven::dispatch(
            (int) $context->agent_id,
            (int) $context->user_id,
            $conversationExternalId,
            $messageExternalId,
            $feedback,
            $llmExternalName,
            $context->country_code,
            now()->toDateTimeImmutable(),
        );
    }

    private function saveMessage(string $conversationId, array $message): void
    {
        $cacheKey = $this->cache->generateConversationKey($conversationId);
        $messages = $this->cache->get($cacheKey, [], $conversationId);
        $messages[] = $message;

        $this->cache->put($cacheKey, $messages, $conversationId);
    }

    private function saveMessages(string $conversationId, array $messages): void
    {
        $cacheKey = $this->cache->generateConversationKey($conversationId);
        $this->cache->put($cacheKey, $messages, $conversationId);
    }

    /**
     * Mark conversation as visible in cache when first message is added.
     */
    private function markConversationAsVisible(string $conversationId): void
    {
        $sessionId = $this->cache->getSessionId();

        // Get session data from cache
        $session = $this->cache->get($sessionId, []);
        $conversations = $session['conversations'] ?? [];

        // Find and update the conversation
        foreach ($conversations as &$conversation) {
            if ($conversation['id'] === $conversationId) {
                $conversation['is_visible'] = true;
                break;
            }
        }

        // Save updated session data
        $this->cache->put($sessionId, [
            'user_id' => $session['user_id'] ?? null,
            'conversations' => $conversations,
        ]);
    }
}
