<?php

namespace Domain\Chat\Services\Memory;

use App\Auth\Models\User;
use App\LLM\Operations\Query\TitleGenerator;
use Domain\Chat\Data\Memory\ConversationData;
use Domain\Chat\Enums\ConversationSource;
use Domain\Chat\Models\ChatConversation;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatSession;
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

        $session = ChatSession::firstOrCreate([
            'external_id' => $this->sessionId,
            'agent_id' => $agent->id,
        ]);

        // Also create the conversation in the database immediately
        // so it exists when widget tries to subscribe or check handoff status
        $conversation = ChatConversation::create([
            'chat_session_id' => $session->id,
            'source' => $source,
            'title' => $title,
        ]);

        $conversationId = $conversation->external_id;

        // Analytics fires from the first MessageSent event (in StoreChatMessageJob)
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
        ChatConversation::where('external_id', $conversationId)->update(['is_visible' => true]);
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
        $conversation = ChatConversation::where('external_id', $conversationId)->first();
        $conversation->messages()->update([
            'content' => null,
        ]);

        if ($deletedBy) {
            $conversation->update(['deleted_by' => $deletedBy]);
        }

        $conversation->delete();

        // Clear conversation from cache
        $conversationKey = $this->cache->generateConversationKey($conversationId);
        $this->cache->forget($conversationKey);

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
        $sessionId = ChatSession::query()->where('external_id', $this->sessionId)->value('id');

        if (! $sessionId) {
            $this->cache->flush();

            return;
        }

        DB::transaction(function () use ($sessionId, $deletedBy): void {
            $conversationIds = ChatConversation::query()
                ->where('chat_session_id', $sessionId)
                ->select('id');

            ChatMessage::query()
                ->whereIn('chat_conversation_id', $conversationIds)
                ->update(['content' => null]);

            ChatConversation::query()
                ->where('chat_session_id', $sessionId)
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

        // Also check if there's a message cache for this conversation
        $conversationKey = $this->cache->generateConversationKey($conversationId);
        $hasMessages = $this->cache->has($conversationKey);

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
