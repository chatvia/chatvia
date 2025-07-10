<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class ChatMemory
{
    private const CHAT_SESSION_PREFIX = 'chat-session:';

    private const CONVERSATION_PREFIX = 'conversation';

    public function updateMessage(string $conversationId, string $userMessageId, string $content): void
    {
        $messages = $this->getMessagesForConversation($conversationId);

        foreach ($messages as &$message) {
            if ($message['id'] === $userMessageId) {
                $message['content'] = $content;
            }
        }

        Cache::put(
            key: $this->getConversationsKey($conversationId),
            value: $messages,
            ttl: now()->addMinutes(15)
        );
    }

    public function getOrGenerateSession(): string
    {
        $chatSession = request()->session()->get(self::CHAT_SESSION_PREFIX);

        if ($chatSession) {
            return $chatSession;
        }

        $chatSessionId = $this->generateSessionId();

        if ($this->isUserAuth()) {

            if ($chatSession = Cache::get($chatSessionId)) {
                request()->session()->put(self::CHAT_SESSION_PREFIX, $chatSession);

                return $chatSession;
            }

            request()->session()->put(self::CHAT_SESSION_PREFIX, $chatSession);

            Cache::forever($chatSessionId, ['conversations' => []]);

            return $chatSession;
        }

        Cache::put(
            key: $chatSessionId,
            value: ['conversations' => []],
            ttl: now()->addDays(3)
        );

        request()->session()->put(self::CHAT_SESSION_PREFIX, $chatSessionId);

        return $chatSessionId;
    }

    private function conversationsKey(): string
    {
        $session = $this->getOrGenerateSession();

        return $session.'.'.self::CONVERSATION_PREFIX;
    }

    public function createConversation(string $title, bool $incognito = false): string
    {
        $conversationId = Str::uuid()->toString();
        $session = $this->getOrGenerateSession();

        $conversations = $this->getConversationsForCurrentSession();

        $conversations[] = [
            'id' => $conversationId,
            'title' => $title,
            'timestamp' => now()->timestamp,
            'visible' => false,
            'incognito' => $incognito,
        ];

        if ($this->isUserAuth()) {
            Cache::forever(
                key: $session,
                value: ['conversations' => $conversations]
            );
        } else {
            Cache::put($session, ['conversations' => $conversations], now()->addDays(3));
        }

        return $conversationId;
    }

    public function addUserMessage(string $conversationId, string $message, string $llmMessage, string $model): string
    {
        return $this->addMessage(
            conversationId: $conversationId,
            role: 'user',
            message: $message,
            model: $model,
            append: ['llm_message' => $llmMessage],
        );
    }

    public function addMessage(string $conversationId, string $role, string $message, ?string $model = null, string $type = 'text', array $append = []): string
    {
        $messageId = Str::uuid()->toString();

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

    private function saveMessage($conversationId, array $message): void
    {
        $cacheId = $this->getConversationsKey($conversationId);

        $messages = Cache::get($cacheId, []);
        $messages[] = $message;

        Cache::forever($cacheId, $messages);
    }

    public function addFeedback() {}

    public function conversationExist($conversationId): bool
    {
        $cacheId = $this->getConversationsKey($conversationId);

        return Cache::has($cacheId);
    }

    private function getConversationsKey($conversationId): string
    {
        $chatSession = $this->getOrGenerateSession();

        return $chatSession.'.conversations.'.$conversationId;
    }

    public function getMessagesForConversation(string $conversationId)
    {
        $cacheId = $this->getConversationsKey($conversationId);

        return Cache::get($cacheId, []);
    }

    public function getLlmMessages(string $conversationId): array
    {
        $cacheId = $this->getConversationsKey($conversationId);

        $messages = Cache::get($cacheId, []);

        if (! $messages) {
            return [];
        }

        return collect($messages)->map(function ($message) {
            return [
                'role' => $message['role'],
                'content' => $message['llm_message'] ?? $message['content'],
            ];
        })->toArray();
    }

    public function getConversationsForCurrentSession(bool $onlyVisible = false, $orderByDate = false): array
    {
        $currentSession = $this->getOrGenerateSession();

        $session = Cache::get($currentSession);

        $conversations = collect($session['conversations'] ?? [])->values();

        if ($conversations) {
            if ($onlyVisible) {
                $conversations = $conversations->filter(fn ($conversation) => $conversation['visible'] === true);
            }

            if ($orderByDate) {
                $conversations = $conversations->sortByDesc('timestamp');
            }
        }

        return $conversations->values()->toArray();
    }

    public function generateSessionId(): string
    {
        if ($this->isUserAuth()) {
            return 'chat-session.user.'.Auth::id();
        }

        return 'chat-session.guest.'.Str::uuid()->toString();
    }

    public function isUserAuth(): bool
    {
        return Auth::check();
    }

    public function updateConversationTitle(string $conversationId, string $title): void
    {
        $conversations = $this->getConversationsForCurrentSession();

        foreach ($conversations as &$conversation) {
            if ($conversation['id'] === $conversationId) {
                $conversation['title'] = Str::of($title)->replace('"', '')->toString();
                $conversation['visible'] = true;
            }
        }

        $chatSession = $this->getOrGenerateSession();

        Cache::forever($chatSession, ['conversations' => $conversations]);
    }

    public function updateConversationTitleFromMessages(string $conversationId, array $messages): void
    {
        $messages = collect($messages)->pluck('content')->implode(', ');

        $newTitle = OpenRouter::completion([
            'model' => 'openai/gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => '
                    You are an AI writer. Your task is to find a meaningful title for the given text.
                    You must return your response in plain text format.
                    Make it concise
                    ',
                ],
                [
                    'role' => 'user',
                    'content' => 'The text is: '.$messages,
                    'max_tokens' => 50,
                ],
            ],
        ])->json('choices.0.message.content');

        $this->updateConversationTitle($conversationId, $newTitle);
    }

    public function deleteConversation(string $conversationId): void
    {
        $cacheId = $this->getConversationsKey($conversationId);
        Cache::forget($cacheId);

        $conversations = $this->getConversationsForCurrentSession();

        foreach ($conversations as $key => $conversation) {
            if ($conversation['id'] === $conversationId) {
                unset($conversations[$key]);
            }
        }

        $chatSession = $this->getOrGenerateSession();
        Cache::forever($chatSession, ['conversations' => $conversations]);
    }

    public function removeMessage($conversationId, $messageId): void
    {
        $messages = $this->getMessagesForConversation($conversationId);

        foreach ($messages as $key => $message) {
            if ($message['id'] === $messageId) {
                unset($messages[$key]);
            }
        }

        $cacheId = $this->getConversationsKey($conversationId);

        Cache::put($cacheId, array_values($messages), now()->addMinutes(15));
    }

    private function redis()
    {
        return Redis::client();
    }
}
