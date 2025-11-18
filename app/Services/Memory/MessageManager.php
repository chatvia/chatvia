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
        }

        $role = $isInputMessage ? 'user' : 'assistant';

        return $this->addMessage(
            conversationId: $conversationId,
            role: $role,
            message: $payload->message,
            messageId: $payload->message_id ?? null,
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
                }
            }
        }

        $this->saveMessages($conversationId, $messages);
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
     */
    public function getLlmMessages(string $conversationId, bool $asText = false): array|string
    {
        $cacheKey = $this->cache->generateConversationKey($conversationId);
        $messages = $this->cache->get($cacheKey, []);

        if (empty($messages)) {
            return $asText ? '' : [];
        }

        // Get last 5 messages for context window
        $messages = array_slice($messages, -5);

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
        $llmMessages = $this->getLlmMessages($conversationId);

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
