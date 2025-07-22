<?php

namespace App\Support\Chat;

use App\Data\Chat\ChatInputPayload;
use App\Data\Chat\ChatOutputPayload;
use App\Models\Agent;
use App\Models\Message;
use App\Support\OpenRouter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ChatMemory
{
    private string $conversationId = '';

    private Agent $agent;

    public function setConversationId(string $conversationId): self
    {
        $this->conversationId = $conversationId;

        return $this;
    }

    public function setAgent(Agent $agent): self
    {
        $this->agent = $agent;

        return $this;
    }

    private function getMessageRetention(): Carbon
    {
        return now()->addDays($this->agent->messageRetentionInDays());
    }

    public function updateMessage(string $userMessageId, array $values): void
    {
        $messages = $this->getMessagesForConversation();

        foreach ($messages as &$message) {
            if ($message['id'] === $userMessageId) {
                foreach ($values as $key => $value) {
                    $message[$key] = $value;

                    if ($key === 'feedback') {
                        Message::where('id', $message['id'])->update([
                            'feedback' => $value ? 'positive' : 'negative',
                        ]);
                    }
                }
            }
        }

        $this->cachePut(
            key: $this->getConversationsKey(),
            value: $messages,
        );
    }

    public function getOrGenerateSession(): string
    {
        return app(ChatSession::class)->getOrGenerateSession();
    }

    public function createPlaygroundConversation(): string
    {
        $conversations = $this->getConversationsForCurrentSession();

        foreach ($conversations as $conversation) {
            if ($conversation['title'] === 'Playground') {
                return $conversation['id'];
            }
        }

        return $this->createConversation('Playground', 'playground.');
    }

    public function createConversation(string $title, string $prefix = ''): string
    {
        $conversationId = $prefix.Str::uuid()->toString();

        $session = $this->getOrGenerateSession();

        $conversations = $this->getConversationsForCurrentSession();

        $conversations[] = [
            'id' => $conversationId,
            'title' => $title,
            'timestamp' => now()->timestamp,
            'visible' => false,
        ];

        $this->cachePut(
            key: $session,
            value: [
                'conversations' => $conversations,
            ],
        );

        return $conversationId;
    }

    public function addMessageViaPayload(ChatInputPayload|ChatOutputPayload $payload): string
    {
        $isInputMessage = $payload instanceof ChatInputPayload;

        $append = [
            'include_in_conversation' => $this->agent->contextWindowEnabled(),
        ];

        if ($isInputMessage) {
            $append['message_for_llm'] = $payload->message_for_llm;
        } else {
            $append['sources'] = $payload->sources;
        }

        $role = $isInputMessage ? 'user' : 'assistant';

        $messageId = $this->addMessage(
            role: $role,
            message: $payload->message,
            messageId: $payload->message_id ?? null,
            model: $payload->model,
            append: $append,
        );

        // Persist it
        \Illuminate\Support\defer(function () use ($payload, $messageId, $isInputMessage, $role) {
            Message::create([
                'id' => $messageId,
                'conversation_id' => $this->conversationId,
                'agent_id' => $this->agent->id,
                'member_id' => $isInputMessage ? $payload->member_id : null,
                'user_id' => $this->agent->user_id,
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

    public function addMessage(string $role, string $message, ?string $messageId = null, ?string $model = null, string $type = 'text', array $append = []): string
    {
        $messageId = $messageId ?? Str::uuid()->toString();

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

        $messages = $this->cacheGet($cacheId);
        $messages[] = $message;

        $this->cachePut(
            key: $cacheId,
            value: $messages,
        );
    }

    public function feedback($messageId, bool $satisfied): void
    {
        $this->updateMessage($messageId, ['satisfied' => $satisfied]);
    }

    public function conversationExist(): bool
    {
        $cacheId = $this->getConversationsKey();

        return $this->cacheHas($cacheId);
    }

    private function getConversationsKey(): string
    {
        $chatSession = $this->getOrGenerateSession();

        return $chatSession.'.conversations.'.$this->conversationId;
    }

    public function getMessageById(string $messageId)
    {
        $messages = collect($this->getMessagesForConversation());

        return $messages->firstWhere('id', $messageId);
    }

    public function getMessagesForConversation(bool $includeLlmMessages = false): array
    {
        $cacheId = $this->getConversationsKey();

        $messages = $this->cacheGet($cacheId);

        $messages = collect($messages);

        if ($includeLlmMessages) {
            return $messages->toArray();
        }

        return $messages->map(function (array $message) {
            unset($message['message_for_llm']);

            return $message;
        })->toArray();
    }

    /**
     * The messages that the llm will receive based on the conversation history and context window size.
     */
    public function getLlmMessages(bool $asText = false): array|string
    {
        //        if (! $this->agent->contextWindowEnabled()) {
        //            return [];
        //        }

        $cacheId = $this->getConversationsKey();

        $messages = $this->cacheGet($cacheId);

        if (! $messages) {
            return [];
        }

        if ($this->agent->contextWindowSize() !== -1) {
            // adjust the context window size
            $messages = array_slice($messages, -$this->agent->contextWindowSize());
        }

        // message_for_llm is the message that LLM will receive, it might contain some LLM-specific details (rag responses)
        $messages = collect($messages)->map(function ($message) {
            return [
                'role' => $message['role'],
                'content' => $message['message_for_llm'] ?? $message['content'],
            ];
        })
            ->filter(function ($item) {
                if (! isset($item['include_in_conversation'])) {
                    return true;
                }

                return $item['include_in_conversation'] === true;
            })
            ->toArray();

        if ($asText) {
            return collect($messages)->pluck('content')->join("\n");
        }

        return $messages;
    }

    public function getConversationsForCurrentSession(bool $onlyVisible = false, $orderByDate = false): array
    {
        $currentSession = $this->getOrGenerateSession();

        $session = $this->cacheGet($currentSession);

        $conversations = collect($session['conversations'] ?? []);

        if ($conversations) {
            if ($onlyVisible) {
                $conversations = $conversations->filter(fn ($conversation) => $conversation['visible'] === true);
            }

            if ($orderByDate) {
                $conversations = $conversations->sortByDesc('timestamp')->values();
            }
        }

        return $conversations->toArray();
    }

    public function isMemberAuth(): bool
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

        $this->cachePut(
            key: $chatSession,
            value: [
                'conversations' => $conversations,
            ],
        );
    }

    public function updateConversationTitleFromMessages(array|string $messages = []): void
    {
        if (! $messages) {
            $messages = $this->getLlmMessages(true);
        }

        if (is_array($messages)) {
            $messages = implode("\n", $messages);
        }

        $newTitle = OpenRouter::completion([
            'model' => 'openai/gpt-4o-mini',
            'max_tokens' => 50,
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
                ],
            ],
        ])->throw()->json('choices.0.message.content');

        $this->updateConversationTitle($newTitle);
    }

    public function deleteConversation(): void
    {
        $cacheId = $this->getConversationsKey();

        $this->cacheForget($cacheId);

        $conversations = $this->getConversationsForCurrentSession();

        foreach ($conversations as $key => $conversation) {
            if ($conversation['id'] === $this->conversationId) {
                unset($conversations[$key]);
            }
        }

        $chatSession = $this->getOrGenerateSession();

        $this->cachePut(
            key: $chatSession,
            value: ['conversations' => $conversations],
        );
    }

    public function deleteAllConversations(): void
    {
        $this->cacheFlush();
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

        $this->cachePut($cacheId, array_values($messages));
    }

    public function getLastMessages(int $number = 3): array
    {
        $llmMessages = $this->getLlmMessages();

        return array_reverse(array_slice($llmMessages, -$number));
    }

    public function deleteMessage(string $messageId): void
    {
        $messages = $this->getMessagesForConversation($this->conversationId);

        foreach ($messages as $key => $message) {
            if ($message['id'] === $messageId) {
                unset($messages[$key], $messages[$key - 1]);
            }
        }

        $this->cachePut(
            key: $this->getConversationsKey(),
            value: $messages,
        );
    }

    public function getChatCache()
    {
        return Cache::driver('chat');
    }

    public function cachePut($key, $value): void
    {
        $this->getChatCache()->put(
            key: $key,
            value: $value,
            ttl: $this->getMessageRetention(),
        );
    }

    public function cacheGet($cacheId)
    {
        return $this->getChatCache()->get($cacheId, []);
    }

    public function cacheHas($cacheId): bool
    {
        return $this->getChatCache()->has($cacheId);
    }

    public function cacheForget($cacheId): bool
    {
        return $this->getChatCache()->forget($cacheId);
    }

    public function cacheFlush(): bool
    {
        return $this->getChatCache()->flush();
    }

    public function getConversationById(string $conversationId)
    {
        $conversations = collect($this->getConversationsForCurrentSession(true));

        return $conversations->firstWhere('id', $conversationId);
    }
}
