<?php

namespace Domain\Conversation\Services\Memory;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Data\Memory\ChatViewerData;
use Domain\Conversation\Data\Memory\ConversationData;
use Domain\Conversation\Data\Memory\LlmMessageData;
use Domain\Conversation\Data\Memory\MessageData;
use Domain\Conversation\Data\Turn\SourceRefData;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\Media\GeneratedMediaNotes;
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
 * The cache holds plain arrays; every method takes and returns the Memory
 * DTOs ({@see ConversationData}, {@see MessageData}), converted at the cache
 * boundary and nowhere else.
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

    public function __construct(private readonly GeneratedMediaNotes $mediaNotes) {}

    /**
     * Bind the memory to a visitor session (and optionally one of its
     * conversations). `$viewer` is the signed-in hosted-chat chatter or staff
     * account, or null for anonymous visitors, the widget and the messaging
     * channels.
     */
    public function withContext(Agent $agent, string $externalSessionId, ?string $conversationId = null, ?ChatViewerData $viewer = null): self
    {
        return $this->withScope(new ChatScopeData($agent, $externalSessionId, $conversationId, $viewer));
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
        $this->scope = new ChatScopeData($scope->agent, $scope->sessionId, $conversationId, $scope->viewer);

        return $this;
    }

    // --- Conversation list ------------------------------------------------

    /**
     * Every conversation in the session, oldest first.
     *
     * @return list<ConversationData>
     */
    public function getConversationsForCurrentSession(): array
    {
        return $this->conversations();
    }

    /**
     * The conversations the visitor can see (named or already used), newest
     * first — the hosted chat's sidebar.
     *
     * @return list<ConversationData>
     */
    public function visibleConversationsNewestFirst(): array
    {
        return collect($this->visibleConversations())
            ->sortByDesc(static fn (ConversationData $conversation): int => $conversation->timestamp)
            ->values()
            ->all();
    }

    /** A visible conversation of this session by id, or null. */
    public function getConversationById(string $conversationId): ?ConversationData
    {
        return collect($this->visibleConversations())
            ->first(static fn (ConversationData $conversation): bool => $conversation->id === $conversationId);
    }

    /**
     * True when the bound conversation is in this session's list or still has
     * cached messages (a tampered or erased id has neither).
     */
    public function conversationExists(): bool
    {
        $conversationId = $this->conversationId();

        $listed = collect($this->conversations())
            ->contains(static fn (ConversationData $conversation): bool => $conversation->id === $conversationId);

        if ($listed) {
            return true;
        }

        return $this->cache()->has($this->cache()->generateConversationKey($conversationId), $conversationId);
    }

    /**
     * The current list before a change. Read it, change it, then hand it to
     * {@see saveConversations()} — reading first keeps a cold-cache rebuild
     * from counting a row the caller is about to add.
     *
     * @return list<ConversationData>
     */
    public function conversations(): array
    {
        $cached = $this->cache()->get($this->scope()->sessionId, []);

        // Trust the cache only when an entry exists AND it was stamped by the
        // current viewer (or nobody). Otherwise — a cold slot after a session
        // reattach, an evicted entry, or one stamped for another chatter —
        // rebuild from chat_conversations so every change sees the full list.
        // Writing back (even when empty) avoids a repeat query on every read.
        $stampedFor = $cached['viewer'] ?? null;
        $viewerMatches = empty($stampedFor) || $this->scope()->viewer?->key() === $stampedFor;

        if ($viewerMatches && $this->cache()->has($this->scope()->sessionId)) {
            return array_map(
                ConversationData::from(...),
                array_values($cached['conversations'] ?? []),
            );
        }

        $conversations = $this->loadConversationsFromDatabase();
        $this->saveConversations($conversations);

        return $conversations;
    }

    /**
     * Replace the session's conversation list, stamped with the current viewer.
     *
     * @param  list<ConversationData>  $conversations
     */
    public function saveConversations(array $conversations): void
    {
        $this->cache()->put($this->scope()->sessionId, [
            'viewer' => $this->scope()->viewer?->key(),
            'conversations' => array_map(
                static fn (ConversationData $conversation): array => $conversation->toArray(),
                array_values($conversations),
            ),
        ]);
    }

    /** Drop a conversation from the session's list (the rows stay as they are). */
    public function forgetConversation(string $conversationId): void
    {
        $remaining = array_filter(
            $this->conversations(),
            static fn (ConversationData $conversation): bool => $conversation->id !== $conversationId,
        );

        $this->saveConversations(array_values($remaining));
    }

    /** Set a conversation's cached title and make it visible in the list. */
    public function renameConversation(string $conversationId, string $title): void
    {
        $conversations = array_map(
            static fn (ConversationData $conversation): ConversationData => $conversation->id === $conversationId
                ? $conversation->renamedTo($title)
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
     * @return list<MessageData>
     */
    public function getMessagesForConversation(): array
    {
        return array_map(
            static fn (MessageData $message): MessageData => $message->forClient(),
            $this->cachedMessages(),
        );
    }

    public function getMessageById(string $messageId): ?MessageData
    {
        return collect($this->getMessagesForConversation())
            ->first(static fn (MessageData $message): bool => $message->id === $messageId);
    }

    /**
     * The last messages in the shape an LLM expects (role + content), using
     * the LLM-facing text when a message has one. A reply that asked for an
     * image, audio file or video ends with a note on each
     * ({@see GeneratedMediaNotes}), never the file. Bounded to
     * `chatvia.agent.history_messages` unless a limit is given, so the context
     * window stays small as the conversation grows.
     *
     * @return list<LlmMessageData>
     */
    public function getLlmMessages(?int $limit = null): array
    {
        $limit ??= (int) config('chatvia.agent.history_messages', 3);
        $messages = collect($this->cachedMessages())->slice(-$limit)->values()->all();

        return collect($this->mediaNotes->forLlm($messages, $this->scope()->agent, $this->conversationId()))
            ->map(static fn (MessageData $message): LlmMessageData => new LlmMessageData(
                role: $message->role,
                content: $message->message_for_llm ?? $message->content,
            ))
            ->values()
            ->all();
    }

    /** The last messages' LLM-facing text joined into one block (for titling). */
    public function llmTranscript(int $limit): string
    {
        return collect($this->getLlmMessages($limit))
            ->map(static fn (LlmMessageData $message): string => $message->content)
            ->join("\n");
    }

    /**
     * Add a message to the bound conversation and mark the conversation
     * visible in the session list.
     */
    public function appendMessage(MessageData $message): void
    {
        $conversationId = $this->conversationId();

        $this->saveMessages([...$this->cachedMessages(), $message]);
        $this->markConversationVisible($conversationId);
    }

    /**
     * The bound conversation's cached messages, including the private
     * LLM-facing text. For Actions that change and save the list.
     *
     * @return list<MessageData>
     */
    public function cachedMessages(): array
    {
        $conversationId = $this->conversationId();

        return array_map(
            MessageData::from(...),
            array_values($this->cache()->get($this->cache()->generateConversationKey($conversationId), [], $conversationId)),
        );
    }

    /**
     * Replace the bound conversation's cached messages.
     *
     * @param  list<MessageData>  $messages
     */
    public function saveMessages(array $messages): void
    {
        $conversationId = $this->conversationId();

        $this->cache()->put(
            $this->cache()->generateConversationKey($conversationId),
            array_map(static fn (MessageData $message): array => $message->toArray(), array_values($messages)),
            $conversationId,
        );
    }

    // --- Sources ----------------------------------------------------------

    /**
     * Remember the RAG sources one assistant message was answered from, so
     * the hosted chat can show them later.
     *
     * @param  list<SourceRefData>  $sources
     */
    public function storeSourcesForMessage(string $messageId, array $sources): void
    {
        $conversationId = $this->conversationId();
        $key = $this->cache()->generateSourcesKey($conversationId);

        $cached = $this->cache()->get($key, [], $conversationId);
        $cached[$messageId] = array_map(static fn (SourceRefData $source): array => $source->toArray(), $sources);

        $this->cache()->put($key, $cached, $conversationId);
    }

    /**
     * Every remembered source of the bound conversation, keyed by message id.
     *
     * @return array<string, list<SourceRefData>>
     */
    public function getSources(): array
    {
        $conversationId = $this->conversationId();

        $cached = $this->cache()->get($this->cache()->generateSourcesKey($conversationId), [], $conversationId);

        return array_map(
            static fn (array $sources): array => array_map(
                SourceRefData::from(...),
                array_values($sources),
            ),
            $cached,
        );
    }

    // --- Internals --------------------------------------------------------

    /**
     * @return list<ConversationData>
     */
    private function visibleConversations(): array
    {
        return array_values(array_filter(
            $this->conversations(),
            static fn (ConversationData $conversation): bool => $conversation->is_visible,
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
            'viewer' => $cached['viewer'] ?? null,
            'conversations' => $conversations,
        ]);
    }

    /**
     * Rebuild the visitor's list from the database. Visitor-cleared threads
     * stay in the database for the workspace's chat log but are left out
     * here, so they never come back into the widget.
     *
     * @return list<ConversationData>
     */
    private function loadConversationsFromDatabase(): array
    {
        // Scoped to the agent: a session id replayed through another agent's
        // widget must not list that session's conversations.
        $session = Session::query()
            ->where('external_id', $this->scope()->sessionId)
            ->where('agent_id', $this->scope()->agent->id)
            ->first();

        if ($session === null) {
            return [];
        }

        return $session->conversations()
            ->whereNull('cleared_at')
            ->orderBy('id')
            ->get()
            ->map(static fn (Conversation $conversation): ConversationData => new ConversationData(
                id: $conversation->external_id,
                title: $conversation->title ?? Conversation::DEFAULT_TITLE,
                timestamp: $conversation->created_at?->timestamp ?? 0,
                is_visible: (bool) $conversation->is_visible,
            ))
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
