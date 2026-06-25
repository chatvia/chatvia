<?php

namespace Domain\Conversation\Services\Memory;

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Data\Memory\ConversationData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;
use Domain\Llm\Operations\Query\TitleGenerator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Manages chat conversations within sessions.
 */
readonly class ConversationManager
{
    public function __construct(
        private ChatCache $cache,
        private string $sessionId
    ) {}

    public function createConversation(string $title, ConversationSource $source): string
    {
        // Ensure the session exists in database
        $agent = $this->cache->getAgent();

        $session = Session::firstOrCreate([
            'external_id' => $this->sessionId,
            'agent_id' => $agent->id,
        ]);

        // Stamp the retention horizon at create-time so the daily prune
        // command can sweep this row deterministically and the chatlog UI
        // can show "Expires on …" without recomputing from the plan. Plan
        // upgrades after this point don't extend an existing conversation;
        // that's intentional — pricing is forward-looking.
        $expiresAt = now()->addDays($agent->messageRetentionInDays());

        // Capture the existing list BEFORE inserting the new row so the
        // DB rebuild in getConversationsFromSession() doesn't double-count
        // the row we're about to add (and then append in-memory below).
        $conversations = $this->getConversationsFromSession();

        // Also create the conversation in the database immediately
        // so it exists when widget tries to subscribe or check handoff status
        $conversation = Conversation::create([
            'chat_session_id' => $session->id,
            'agent_id' => $agent->id,
            'source' => $source,
            'title' => $title,
            'expires_at' => $expiresAt,
        ]);

        $conversationId = $conversation->external_id;

        // Stamp the ownership marker under (conversation, user) tags so
        // EnsureChatIsAccessible can confirm the current chatter owns
        // this conversation on protected agents without a DB join. See
        // ChatCache::rememberOwner() for the tag scheme.
        ChatCache::rememberOwner($conversationId, $this->getCurrentUser()?->id, $agent->workspaceExternalId());

        // Analytics fires from the first MessageSent event (in StoreMessageJob)
        // — country is known by then. Firing here with null country would bucket
        // every conversation under the "--" sentinel and miss the country panel.

        $newConversation = new ConversationData(
            id: $conversationId,
            title: $title,
            timestamp: now()->timestamp,
            is_visible: false,
        );

        $conversations[] = $newConversation->toArray();

        $this->saveSessionData($conversations);

        return $conversationId;
    }

    public function updateConversationTitle(string $conversationId, string $title): void
    {
        $cleanTitle = Str::of($title)->replace('"', '')->toString();
        $conversations = $this->getConversationsFromSession();

        foreach ($conversations as &$conversation) {
            if ($conversation['id'] === $conversationId) {
                $conversation['title'] = $cleanTitle;
                $conversation['is_visible'] = true;
            }
        }

        $this->saveSessionData($conversations);

        // Persist title to DB too so a later cache rebuild from
        // chat_conversations doesn't reset the sidebar to "New Chat".
        // Update the loaded model (not the query builder) so the `title`
        // encrypted cast is applied — a builder ->update() writes raw/plaintext.
        Conversation::where('external_id', $conversationId)->first()?->update([
            'title' => $cleanTitle,
            'is_visible' => true,
        ]);
    }

    public function updateConversationTitleFromMessages(string $conversationId, array|string $messages = []): void
    {
        if (is_array($messages)) {
            $messages = implode("\n", $messages);
        }

        if (empty($messages)) {
            return;
        }

        $newTitle = app(TitleGenerator::class)->generate($messages);

        $this->updateConversationTitle($conversationId, $newTitle);
    }

    public function deleteConversation(string $conversationId, ?string $deletedBy = null): void
    {
        // Soft delete the conversation
        $conversation = Conversation::where('external_id', $conversationId)->first();
        $conversation->messages()->update([
            'content' => null,
        ]);

        if ($deletedBy) {
            $conversation->update(['deleted_by' => $deletedBy]);
        }

        $conversation->delete();

        // Flush every entry tagged with this conversation (messages,
        // sources, anything else conversation-scoped) in one call so
        // Redis matches the DB without waiting on per-entry TTL.
        $this->cache->flushConversation($conversationId);

        // Remove from session conversations list
        $conversations = $this->getConversationsFromSession();

        foreach ($conversations as $key => $conversation) {
            if ($conversation['id'] === $conversationId) {
                unset($conversations[$key]);
            }
        }

        $this->saveSessionData(array_values($conversations));
    }

    public function deleteAllConversations(?string $deletedBy = null): void
    {
        $sessionId = Session::query()->where('external_id', $this->sessionId)->value('id');

        if (! $sessionId) {
            $this->cache->flush();

            return;
        }

        DB::transaction(function () use ($sessionId, $deletedBy): void {
            $conversationIds = Conversation::query()
                ->where('chat_session_id', $sessionId)
                ->pluck('id');

            // Wipe attachment files (S3 + row) before the bulk soft-delete
            // below. The bulk update skips model events, so the per-row
            // cascade on Conversation::booted() never fires — run it
            // here explicitly so uploads don't outlive the conversations.
            Attachment::query()
                ->whereIn('chat_conversation_id', $conversationIds)
                ->get()
                ->each(static fn (Attachment $a) => $a->delete());

            Message::query()
                ->whereIn('chat_conversation_id', $conversationIds)
                ->update(['content' => null]);

            Conversation::query()
                ->whereIn('id', $conversationIds)
                ->update([
                    'deleted_by' => $deletedBy,
                    'deleted_at' => now(),
                    'updated_at' => now(),
                ]);
        });

        $this->cache->flush();
    }

    /**
     * Clear a single conversation from the *visitor's* widget — a view reset,
     * not erasure. The DB row stays alive (deleted_at IS NULL) with its message
     * content intact, so the customer keeps full chat-log + analytics
     * visibility; only the visitor stops seeing it. `cleared_at` is the marker
     * {@see loadConversationsFromDatabase()} filters on so a cold-cache rebuild
     * doesn't resurrect the thread into the widget list.
     *
     * Contrast {@see deleteConversation()}, which nulls content and soft-deletes
     * — that is erasure and stays reserved for the admin/owner delete path.
     */
    public function clearConversationForVisitor(string $conversationId): void
    {
        $conversation = Conversation::where('external_id', $conversationId)->first();

        if ($conversation === null) {
            return;
        }

        // `deleted_by = 'user'` records WHO hid it (varchar(10)); the customer
        // UI can surface a "cleared by visitor" marker off this + cleared_at.
        $conversation->update([
            'cleared_at' => now(),
            'deleted_by' => 'user',
        ]);

        // Drop the live turn state / cached messages so the visitor truly starts
        // fresh — the persisted record in Postgres is the customer's copy.
        $this->cache->flushConversation($conversationId);

        $conversations = $this->getConversationsFromSession();

        foreach ($conversations as $key => $existing) {
            if ($existing['id'] === $conversationId) {
                unset($conversations[$key]);
            }
        }

        $this->saveSessionData(array_values($conversations));
    }

    /**
     * Clear every conversation in the current session from the visitor's widget.
     * Same view-reset semantics as {@see clearConversationForVisitor()} — content
     * and attachments are preserved for the customer; nothing is soft-deleted.
     */
    public function clearAllConversationsForVisitor(): void
    {
        $sessionId = Session::query()->where('external_id', $this->sessionId)->value('id');

        if (! $sessionId) {
            $this->cache->flush();

            return;
        }

        Conversation::query()
            ->where('chat_session_id', $sessionId)
            ->whereNull('cleared_at')
            ->update([
                'cleared_at' => now(),
                'deleted_by' => 'user',
                'updated_at' => now(),
            ]);

        $this->cache->flush();
    }

    public function getConversationById(string $conversationId): ?array
    {
        return collect($this->getConversationsForSession(onlyVisible: true))
            ->firstWhere('id', $conversationId);
    }

    public function conversationExists(string $conversationId): bool
    {
        // Check if conversation exists in session's conversation list
        $conversations = $this->getConversationsFromSession();
        $inSession = collect($conversations)->contains('id', $conversationId);

        // Also check if there's a message cache for this conversation.
        // Pass conversationId so the lookup uses the same per-conversation
        // tag combination the writes use.
        $conversationKey = $this->cache->generateConversationKey($conversationId);
        $hasMessages = $this->cache->has($conversationKey, $conversationId);

        return $inSession || $hasMessages;
    }

    public function getConversationsForSession(bool $onlyVisible = false, bool $orderByDate = false): array
    {
        $conversations = collect($this->getConversationsFromSession());

        if ($onlyVisible) {
            $conversations = $conversations->filter(fn ($conversation): bool => $conversation['is_visible'] === true);
        }

        if ($orderByDate) {
            $conversations = $conversations->sortByDesc('timestamp');
        }

        return $conversations->values()->toArray();
    }

    private function loadConversationsFromDatabase(): array
    {
        $session = Session::query()
            ->where('external_id', $this->sessionId)
            ->first();

        if ($session === null) {
            return [];
        }

        // Exclude visitor-cleared threads — they stay in the DB for the customer
        // but must not rebuild into the widget's conversation list. This filter
        // is intentionally only on the visitor read; the customer's chat log
        // queries Conversation::forAgent() directly and is unaffected.
        return $session->conversations()
            ->whereNull('cleared_at')
            ->orderBy('id')
            ->get()
            ->map(fn (Conversation $conversation): array => (new ConversationData(
                id: $conversation->external_id,
                title: $conversation->title ?? 'New Chat',
                timestamp: $conversation->created_at?->timestamp ?? 0,
                is_visible: (bool) $conversation->is_visible,
            ))->toArray())
            ->all();
    }

    public function isMemberAuthenticated(): bool
    {
        return Auth::guard('web')->check();
    }

    private function getCurrentUser(): ?User
    {
        return Auth::guard('web')->user();
    }

    private function saveSessionData(array $conversations): void
    {
        $this->cache->put($this->sessionId, [
            'user_id' => $this->getCurrentUser()?->id,
            'conversations' => $conversations,
        ]);
    }

    private function getConversationsFromSession(): array
    {
        $session = $this->cache->get($this->sessionId, []);

        $userIdMatches = empty($session['user_id'])
            || $this->getCurrentUser()?->id === $session['user_id'];

        // Trust the cache only when an entry exists AND it was stamped by
        // the current user (or nobody). Otherwise — cold slot after a
        // TurnState reattach, evicted entry, or stale entry stamped under
        // a different chatter — rebuild from chat_conversations so the
        // mutation paths (create/update/delete) all see the full history.
        // Writing back (even when empty) prevents a repeat DB query on
        // every read while no conversations exist.
        if ($this->cache->has($this->sessionId) && $userIdMatches) {
            return $session['conversations'] ?? [];
        }

        $conversations = $this->loadConversationsFromDatabase();
        $this->saveSessionData($conversations);

        return $conversations;
    }
}
