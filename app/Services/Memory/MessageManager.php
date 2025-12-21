<?php

namespace App\Services\Memory;

use App\Data\Chat\ChatInputPayload;
use App\Data\Chat\ChatOutputPayload;
use App\Models\Agent;
use App\Models\ChatMessage;
use Illuminate\Support\Str;

/**
 * Manages chat messages within conversations.
 */
class MessageManager
{
    public function __construct(
        private readonly ChatCache $cache,
        private readonly Agent $agent
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
        $messageId = $messageId ?? Str::ulid()->toString();

        $formattedMessage = [
            'id' => $messageId,
            'role' => $role,
            'content' => $message,
            'type' => $type,
            'timestamp' => now()->timestamp,
        ];

        // Only add read_at for human agent messages (handoff mode)
        if ($role === 'agent') {
            $formattedMessage['read_at'] = null;
        }

        if ($model) {
            $formattedMessage['model'] = $model;
        }

        $this->saveMessage($conversationId, array_merge($formattedMessage, $append));

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
            // For output messages, set answer_found flag
            $append['answer_found'] = $payload->answer_found ?? true;
        }

        $role = $isInputMessage ? 'user' : 'assistant';

        return $this->addMessage(
            conversationId: $conversationId,
            role: $role,
            message: $payload->message,
            messageId: $payload->message_id,
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
                    $message[$key] = $value;

                    // Handle feedback persistence to database
                    if ($key === 'feedback') {
                        ChatMessage::where('id', $message['id'])->update([
                            'feedback' => $value ? 'positive' : 'negative',
                        ]);
                    }

                    // Handle read_at persistence to database
                    if ($key === 'read_at') {
                        ChatMessage::where('id', $message['id'])->update([
                            'read_at' => $value,
                        ]);
                    }

                    // Handle answer_found persistence to database
                    if ($key === 'answer_found') {
                        ChatMessage::where('id', $message['id'])->update([
                            'answer_found' => $value,
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

                // Persist to database
                ChatMessage::where('id', $message['id'])->update([
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

        foreach ($messages as $key => $message) {
            if ($message['id'] === $messageId) {
                // Delete both the message and the previous one (user-assistant pair)
                unset($messages[$key], $messages[$key - 1]);
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

    public function getMessageById(string $conversationId, string $messageId): ?array
    {
        return collect($this->getMessagesForConversation($conversationId))
            ->firstWhere('id', $messageId);
    }

    public function getMessagesForConversation(string $conversationId, bool $includeLlmMessages = false): array
    {
        $cacheKey = $this->cache->generateConversationKey($conversationId);
        $messages = $this->cache->get($cacheKey, []);

        if ($includeLlmMessages) {
            return $messages;
        }

        return collect($messages)->map(function (array $message) {
            unset($message['message_for_llm']);

            return $message;
        })->toArray();
    }

    /**
     * Get messages formatted for LLM consumption.
     * Filters out assistant messages where answer_found is false and their preceding user messages.
     */
    public function getLlmMessages(string $conversationId, bool $asText = false, int $limit = 2): array|string
    {
        $cacheKey = $this->cache->generateConversationKey($conversationId);
        $messages = $this->cache->get($cacheKey, []);

        if (empty($messages)) {
            return $asText ? '' : [];
        }

        // Filter out assistant messages where answer_found is false and their preceding user messages
        $messagesToExclude = [];
        $previousUserMessage = null;

        foreach ($messages as $index => $message) {
            if ($message['role'] === 'user') {
                $previousUserMessage = $index;
            } elseif ($message['role'] === 'assistant') {
                if (($message['answer_found'] ?? true) === false) {
                    // Exclude this assistant message
                    $messagesToExclude[] = $index;
                    // Also exclude the previous user message if it exists
                    if ($previousUserMessage !== null) {
                        $messagesToExclude[] = $previousUserMessage;
                    }
                }
                // Reset previous user message after processing assistant message
                $previousUserMessage = null;
            }
        }

        $filteredMessages = collect($messages)
            ->reject(function ($message, $index) use ($messagesToExclude) {
                return in_array($index, $messagesToExclude);
            })
            ->values()
            ->toArray();

        // Get last N messages for context window
        $messages = array_slice($filteredMessages, -$limit);

        $formattedMessages = collect($messages)
            ->map(fn ($message) => [
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

    public function addFeedback(string $conversationId, string $messageId, bool $satisfied): void
    {
        $this->updateMessage($conversationId, $messageId, ['feedback' => $satisfied]);
    }

    private function saveMessage(string $conversationId, array $message): void
    {
        $cacheKey = $this->cache->generateConversationKey($conversationId);
        $messages = $this->cache->get($cacheKey, []);
        $messages[] = $message;

        $this->cache->put($cacheKey, $messages);
    }

    private function saveMessages(string $conversationId, array $messages): void
    {
        $cacheKey = $this->cache->generateConversationKey($conversationId);
        $this->cache->put($cacheKey, $messages);
    }
}
