<?php

namespace Domain\Conversation\Services\Memory;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Data\Memory\ConversationData;
use Domain\Conversation\Data\Memory\MessageData;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use InvalidArgumentException;

/**
 * The cached view of one visitor session: its conversation list and, for one
 * conversation, its messages and retrieved RAG sources. This is the live chat
 * memory every surface reads to render history and build the next LLM turn.
 *
 * Pure cache access: it never writes the database and never fires events. The
 * operations that change a conversation (start, rename, delete, clear,
 * feedback, read receipts, …) are Actions that use this repository.
 *
 * Bind it to a session with {@see withScope()} (or {@see withContext()}) first.
 * The conversation list falls back to `chat_conversations` when the cache is
 * cold, or when it was stamped for a different signed-in viewer, and the
 * rebuilt list is written back. Messages and sources have no database
 * fallback — the database copy is the chat log, not the live memory.
 */
class ChatMemory
{
    private ?ChatScopeData $scope = null;

    private ?ChatCache $cache = null;

    /**
     * Bind the memory to a visitor session (and optionally one of its
     * conversations). `$viewerId` is the signed-in hosted-chat user, or null
     * for anonymous visitors, the widget and the messaging channels.
     */
    public function withContext(Agent $agent, string $externalSessionId, ?string $conversationId = null, ?int $viewerId = null): self
    {
        return $this->withScope(new ChatScopeData($agent, $externalSessionId, $conversationId, $viewerId));
    }

    public function withScope(ChatScopeData $scope): self
    {
        $this->scope = $scope;
        $this->cache = new ChatCache($scope->sessionId, $scope->agent);

        return $this;
    }

    /**
     * Narrow an already-bound memory to one conversation — for callers that
     * learn the conversation id only after the session is bound.
     */
    public function setConversationId(?string $conversationId = null): self
    {
        $scope = $this->scope();
        $this->scope = new ChatScopeData($scope->agent, $scope->sessionId, $conversationId, $scope->viewerId);

        return $this;
    }

    // --- Conversation list ------------------------------------------------

    /**
     * Every conversation in the session, oldest first, as the cached arrays
     * (the {@see ConversationData} shape the chat clients read).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getConversationsForCurrentSession(): array
    {
        return $this->conversations();
    }

    /**
     * The conversations the visitor can see (named or already used), newest
     * first — the hosted chat's sidebar.
     *
     * @return array<int, array<string, mixed>>
     */
    public function visibleConversationsNewestFirst(): array
    {
        return collect($this->visibleConversations())
            ->sortByDesc('timestamp')
            ->values()
            ->all();
    }

    /** A visible conversation of this session by id, or null. */
    public function getConversationById(string $conversationId): ?ConversationData
    {
        $conversation = collect($this->visibleConversations())->firstWhere('id', $conversationId);

        return $conversation !== null ? ConversationData::from($conversation) : null;
    }

    /**
     * True when the bound conversation is in this session's list or still has
     * cached messages (a tampered or erased id has neither).
     */
    public function conversationExists(): bool
    {
        $conversationId = $this->conversationId();

        if (collect($this->conversations())->contains('id', $conversationId)) {
            return true;
        }

        return $this->cache()->has($this->cache()->generateConversationKey($conversationId), $conversationId);
    }

    /**
     * The current list before a change. Read it, change it, then hand it to
     * {@see saveConversations()} — reading first keeps a cold-cache rebuild
     * from counting a row the caller is about to add.
     *
     * @return array<int, array<string, mixed>>
     */
    public function conversations(): array
    {
        $cached = $this->cache()->get($this->scope()->sessionId, []);

        // Trust the cache only when an entry exists AND it was stamped by the
        // current viewer (or nobody). Otherwise — a cold slot after a session
        // reattach, an evicted entry, or one stamped for another chatter —
        // rebuild from chat_conversations so every change sees the full list.
        // Writing back (even when empty) avoids a repeat query on every read.
        $stampedFor = $cached['user_id'] ?? null;
        $viewerMatches = empty($stampedFor) || $this->scope()->viewerId === $stampedFor;

        if ($viewerMatches && $this->cache()->has($this->scope()->sessionId)) {
            return $cached['conversations'] ?? [];
        }

        $conversations = $this->loadConversationsFromDatabase();
        $this->saveConversations($conversations);

        return $conversations;
    }

    /**
     * Replace the session's conversation list, stamped with the current viewer.
     *
     * @param  array<int, array<string, mixed>>  $conversations
     */
    public function saveConversations(array $conversations): void
    {
        $this->cache()->put($this->scope()->sessionId, [
            'user_id' => $this->scope()->viewerId,
            'conversations' => $conversations,
        ]);
    }

    /** Drop a conversation from the session's list (the rows stay as they are). */
    public function forgetConversation(string $conversationId): void
    {
        $remaining = array_filter(
            $this->conversations(),
            static fn (array $conversation): bool => $conversation['id'] !== $conversationId,
        );

        $this->saveConversations(array_values($remaining));
    }

    /** Set a conversation's cached title and make it visible in the list. */
    public function renameConversation(string $conversationId, string $title): void
    {
        $conversations = array_map(
            static fn (array $conversation): array => $conversation['id'] === $conversationId
                ? [...$conversation, 'title' => $title, 'is_visible' => true]
                : $conversation,
            $this->conversations(),
        );

        $this->saveConversations($conversations);
    }

    /** Remove every cached entry of this session. */
    public function flushSession(): void
    {
        $this->cache()->flush();
    }

    /**
     * Remove every cached entry tagged with a conversation (its messages,
     * sources and ownership marker) in one call, so the cache matches the
     * database without waiting for the entries to expire.
     */
    public function flushConversation(string $conversationId): void
    {
        $this->cache()->flushConversation($conversationId);
    }

    // --- Messages ---------------------------------------------------------

    /**
     * The bound conversation's messages as the chat clients receive them —
     * without the private LLM-facing text.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMessagesForConversation(): array
    {
        return array_map(static function (array $message): array {
            unset($message['message_for_llm']);

            return MessageData::from($message)->toArray();
        }, $this->cachedMessages());
    }

    public function getMessageById(string $messageId): ?MessageData
    {
        $message = collect($this->getMessagesForConversation())->firstWhere('id', $messageId);

        return $message !== null ? MessageData::from($message) : null;
    }

    /**
     * The last messages in the shape an LLM expects (role + content), using
     * the LLM-facing text when a message has one. Bounded to
     * `chatvia.agent.history_messages` unless a limit is given, so the context
     * window stays small as the conversation grows.
     *
     * @return array<int, array{role: string, content: string}>
     */
    public function getLlmMessages(?int $limit = null): array
    {
        $limit ??= (int) config('chatvia.agent.history_messages', 10);

        return collect($this->cachedMessages())
            ->map(static fn (array $message): array => [
                'role' => $message['role'],
                'content' => $message['message_for_llm'] ?? $message['content'],
            ])
            ->slice(-$limit)
            ->values()
            ->all();
    }

    /** The last messages' LLM-facing text joined into one block (for titling). */
    public function llmTranscript(int $limit): string
    {
        return collect($this->getLlmMessages($limit))->pluck('content')->join("\n");
    }

    /**
     * Add a message to the bound conversation and mark the conversation
     * visible in the session list.
     */
    public function appendMessage(MessageData $message): void
    {
        $conversationId = $this->conversationId();

        $this->saveMessages([...$this->cachedMessages(), $message->toArray()]);
        $this->markConversationVisible($conversationId);
    }

    /**
     * The bound conversation's raw cached messages, including the private
     * LLM-facing text. For Actions that change and save the list.
     *
     * @return array<int, array<string, mixed>>
     */
    public function cachedMessages(): array
    {
        $conversationId = $this->conversationId();

        return $this->cache()->get($this->cache()->generateConversationKey($conversationId), [], $conversationId);
    }

    /**
     * Replace the bound conversation's cached messages.
     *
     * @param  array<int, array<string, mixed>>  $messages
     */
    public function saveMessages(array $messages): void
    {
        $conversationId = $this->conversationId();

        $this->cache()->put($this->cache()->generateConversationKey($conversationId), $messages, $conversationId);
    }

    // --- Sources ----------------------------------------------------------

    /**
     * Remember the RAG sources one assistant message was answered from, so
     * the hosted chat can show them later.
     *
     * @param  array<int, array<string, mixed>>  $sources
     */
    public function storeSourcesForMessage(string $messageId, array $sources): void
    {
        $conversationId = $this->conversationId();
        $key = $this->cache()->generateSourcesKey($conversationId);

        $cached = $this->cache()->get($key, [], $conversationId);
        $cached[$messageId] = $sources;

        $this->cache()->put($key, $cached, $conversationId);
    }

    /**
     * Every remembered source of the bound conversation, keyed by message id.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function getSources(): array
    {
        $conversationId = $this->conversationId();

        return $this->cache()->get($this->cache()->generateSourcesKey($conversationId), [], $conversationId);
    }

    // --- Internals --------------------------------------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    private function visibleConversations(): array
    {
        return array_values(array_filter(
            $this->conversations(),
            static fn (array $conversation): bool => $conversation['is_visible'] === true,
        ));
    }

    /**
     * Flip a conversation to visible in the cached list without the cold-cache
     * rebuild: the list entry is left as it is when the session data is gone,
     * and the existing viewer stamp is kept.
     */
    private function markConversationVisible(string $conversationId): void
    {
        $sessionId = $this->scope()->sessionId;
        $cached = $this->cache()->get($sessionId, []);

        $conversations = array_map(
            static fn (array $conversation): array => $conversation['id'] === $conversationId
                ? [...$conversation, 'is_visible' => true]
                : $conversation,
            $cached['conversations'] ?? [],
        );

        $this->cache()->put($sessionId, [
            'user_id' => $cached['user_id'] ?? null,
            'conversations' => $conversations,
        ]);
    }

    /**
     * Rebuild the visitor's list from the database. Visitor-cleared threads
     * stay in the database for the workspace's chat log but are left out
     * here, so they never come back into the widget.
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadConversationsFromDatabase(): array
    {
        $session = Session::query()
            ->where('external_id', $this->scope()->sessionId)
            ->first();

        if ($session === null) {
            return [];
        }

        return $session->conversations()
            ->whereNull('cleared_at')
            ->orderBy('id')
            ->get()
            ->map(static fn (Conversation $conversation): array => new ConversationData(
                id: $conversation->external_id,
                title: $conversation->title ?? Conversation::DEFAULT_TITLE,
                timestamp: $conversation->created_at?->timestamp ?? 0,
                is_visible: (bool) $conversation->is_visible,
            )->toArray())
            ->all();
    }

    private function scope(): ChatScopeData
    {
        return $this->scope ?? throw new InvalidArgumentException('Bind the chat memory with withScope() before using it.');
    }

    private function cache(): ChatCache
    {
        return $this->cache ?? throw new InvalidArgumentException('Bind the chat memory with withScope() before using it.');
    }

    private function conversationId(): string
    {
        return $this->scope()->conversationId
            ?? throw new InvalidArgumentException('Conversation ID must be set before performing this operation');
    }
}
