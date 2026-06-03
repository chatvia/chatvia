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

        // Also create the conversation in the database immediately
        // so it exists when widget tries to subscribe or check handoff status
        $conversation = Conversation::create([
            'chat_session_id' => $session->id,
            'source' => $source,
            'title' => $title,
            'expires_at' => $expiresAt,
        ]);

        $conversationId = $conversation->external_id;

        // Analytics fires from the first MessageSent event (in StoreMessageJob)
        // — country is known by then. Firing here with null country would bucket
        // every conversation under the "--" sentinel and miss the country panel.

        $conversations = $this->getConversationsFromSession();

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
        $conversations = $this->getConversationsFromSession();

        foreach ($conversations as &$conversation) {
            if ($conversation['id'] === $conversationId) {
                $conversation['title'] = Str::of($title)->replace('"', '')->toString();
                $conversation['is_visible'] = true;
            }
        }

        $this->saveSessionData($conversations);

        // Also update database visibility
        Conversation::where('external_id', $conversationId)->update(['is_visible' => true]);
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
        $session = $this->cache->get($this->sessionId, []);

        // Check if session belongs to current user for private agents
        if (! empty($session['user_id']) && $this->getCurrentUser()?->id !== $session['user_id']) {
            return [];
        }

        $conversations = collect($session['conversations'] ?? []);

        if ($onlyVisible) {
            $conversations = $conversations->filter(fn ($conversation): bool => $conversation['is_visible'] === true);
        }

        if ($orderByDate) {
            $conversations = $conversations->sortByDesc('timestamp');
        }

        return $conversations->values()->toArray();
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

        return $session['conversations'] ?? [];
    }
}
