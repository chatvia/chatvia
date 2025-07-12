<?php

namespace App\Support;

use App\Data\Chat\ChatInputPayload;
use App\Data\Chat\ChatOutputPayload;
use App\Models\Message;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ChatMemory
{
    private const CHAT_SESSION_PREFIX = 'chat-session:';

    private const CONVERSATION_PREFIX = 'conversation';

    private string $conversationId = "";

    private \App\Models\Agent $agent;

    public function setConversationId($conversationId): self
    {
        $this->conversationId = $conversationId;

        return $this;
    }

    public function setAgent(\App\Models\Agent $agent): self
    {
        $this->agent = $agent;

        return $this;
    }

    public function updateMessage(string $userMessageId, string $content): void
    {
        $messages = $this->getMessagesForConversation($this->conversationId);

        foreach ($messages as &$message) {
            if ($message['id'] === $userMessageId) {
                $message['content'] = $content;
            }
        }

        Cache::put(
            key: $this->getConversationsKey($this->conversationId),
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

    public function addMessageViaPayload(ChatInputPayload|ChatOutputPayload $payload): string
    {
        $isInputMessage = $payload instanceof ChatInputPayload;

        $append = [];

        if ($isInputMessage) {
            $append[] = [
                'message_for_llm' => $payload->message_for_llm,
            ];
        }

        $role = $isInputMessage ? 'user' : 'assistant';

        $messageId = $this->addMessage(
            role: $role,
            message: $payload->message,
            model: $payload->model,
            append: $append,
        );

        // Persist it
        \Illuminate\Support\defer(function() use ($payload, $messageId, $isInputMessage, $role) {
            Message::create([
                'id' => $messageId,
                'conversation_id' => $this->conversationId,
                'agent_id' => $this->agent->id,
                'member_id' => $isInputMessage ? $payload->member_id : null,
                'content' => $payload->message,
                'role' => $role,
                'model' => $payload->model,
                'response_time_ms' => $payload?->response_time_ms ?? 0,
                'generation_id' => $payload?->generation_id ?? null,
                'token_count' => $payload?->token_count ?? 0,
            ]);
        });

        return $messageId;
    }

    public function addMessage(string $role, string $message, ?string $model = null, string $type = 'text', array $append = []): string
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

        $this->saveMessage(array_merge($formattedMessage, $append));

        return $messageId;
    }

    private function saveMessage(array $message): void
    {
        $cacheId = $this->getConversationsKey();

        $messages = Cache::get($cacheId, []);
        $messages[] = $message;

        Cache::forever($cacheId, $messages);
    }

    public function addFeedback() {}

    public function conversationExist(): bool
    {
        $cacheId = $this->getConversationsKey($this->conversationId);

        return Cache::has($cacheId);
    }

    private function getConversationsKey(): string
    {
        $chatSession = $this->getOrGenerateSession();

        return $chatSession.'.conversations.'.$this->conversationId;
    }

    public function getMessagesForConversation(bool $includeLlmMessages = false): array
    {
        $cacheId = $this->getConversationsKey();

        $messages = collect(Cache::get($cacheId, []))->toArray();

        if ($includeLlmMessages) {
            return $messages;
        }


        return $messages->map(function (array $message) {
            unset($message['message_for_llm']);
            return $message;
        })->toArray();
    }

    public function getLlmMessages(bool $asText = false): array|string
    {
        $cacheId = $this->getConversationsKey();

        $messages = Cache::get($cacheId, []);

        if (! $messages) {
            return [];
        }

        // message_for_llm is the message that LLM will receive, it might contain some LLM-specific details (rag responses)
        $messages = collect($messages)->map(function ($message) {
            return [
                'role' => $message['role'],
                'content' => $message['message_for_llm'] ?? $message['content'],
            ];
        })->toArray();

        if ($asText) {
            return collect($messages)->pluck('content')->join("\n");
        }

        return $messages;
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

    public function updateConversationTitle(string $title): void
    {
        $conversations = $this->getConversationsForCurrentSession();

        foreach ($conversations as &$conversation) {
            if ($conversation['id'] === $this->conversationId) {
                $conversation['title'] = Str::of($title)->replace('"', '')->toString();
                $conversation['visible'] = true;
            }
        }

        $chatSession = $this->getOrGenerateSession();

        Cache::forever($chatSession, ['conversations' => $conversations]);
    }

    public function updateConversationTitleFromMessages(): void
    {
        $messages = $this->getLlmMessages(asText: true);

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

        $this->updateConversationTitle($newTitle);
    }

    public function deleteConversation(): void
    {
        $cacheId = $this->getConversationsKey();
        Cache::forget($cacheId);

        $conversations = $this->getConversationsForCurrentSession();

        foreach ($conversations as $key => $conversation) {
            if ($conversation['id'] === $this->conversationId) {
                unset($conversations[$key]);
            }
        }

        $chatSession = $this->getOrGenerateSession();
        Cache::forever($chatSession, ['conversations' => $conversations]);
    }

    public function removeMessage($messageId): void
    {
        $messages = $this->getMessagesForConversation($this->conversationId);

        foreach ($messages as $key => $message) {
            if ($message['id'] === $messageId) {
                unset($messages[$key]);
            }
        }

        $cacheId = $this->getConversationsKey();

        Cache::put($cacheId, array_values($messages), now()->addMinutes(15));
    }

    public function getLastMessageForLlm()
    {
        $llmMessages = $this->getLlmMessages();

        return $llmMessages[array_key_last($llmMessages)] ?? null;
    }
}
