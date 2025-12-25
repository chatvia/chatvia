<?php

namespace App\Services\Memory;

use App\Data\Memory\ConversationData;
use App\Models\ChatConversation;
use App\Models\ChatSession;
use App\Models\User;
use App\Services\Infrastructure\LLM\Operations\TitleGenerator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Manages chat conversations within sessions.
 */
class ConversationManager
{
    public function __construct(
        private readonly ChatCache $cache,
        private readonly string $sessionId
    ) {}

    public function createConversation(string $title): string
    {
        $conversationId = Str::ulid()->toString();

        $conversations = $this->getConversationsFromSession();

        $newConversation = new ConversationData(
            id: $conversationId,
            title: $title,
            timestamp: now()->timestamp,
            visible: false,
        );

        $conversations[] = $newConversation->toArray();

        $this->saveSessionData($conversations);

        // Ensure the session exists in database
        $agent = $this->cache->getAgent();
        ChatSession::firstOrCreate([
            'id' => $this->sessionId,
            'agent_id' => $agent->id,
        ]);

        // Also create the conversation in the database immediately
        // so it exists when widget tries to subscribe or check handoff status
        ChatConversation::firstOrCreate([
            'id' => $conversationId,
            'chat_session_id' => $this->sessionId,
        ]);

        return $conversationId;
    }

    public function updateConversationTitle(string $conversationId, string $title): void
    {
        $conversations = $this->getConversationsFromSession();

        $conversations = array_map(function (array $conversation) use ($conversationId, $title) {
            if ($conversation['id'] === $conversationId) {
                $conversationData = ConversationData::from($conversation);

                $updatedConversation = new ConversationData(
                    id: $conversationData->id,
                    title: Str::of($title)->replace('"', '')->toString(),
                    timestamp: $conversationData->timestamp,
                    visible: true,
                    messages: $conversationData->messages,
                );

                return $updatedConversation->toArray();
            }

            return $conversation;
        }, $conversations);

        $this->saveSessionData($conversations);
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

    public function deleteConversation(string $conversationId): void
    {
        // Soft delete the conversation
        ChatConversation::where('id', $conversationId)->delete();

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

    public function deleteAllConversations(): void
    {
        // Soft delete all conversations for this session
        ChatConversation::where('chat_session_id', $this->sessionId)->delete();

        // Clear cache
        $this->cache->flush();
    }

    public function getConversationById(string $conversationId): ?ConversationData
    {
        $conversation = collect($this->getConversationsForSession(onlyVisible: true))
            ->firstWhere('id', $conversationId);

        return $conversation ? ConversationData::from($conversation) : null;
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
            $conversations = $conversations->filter(fn ($conversation) => $conversation['visible'] === true);
        }

        if ($orderByDate) {
            $conversations = $conversations->sortByDesc('timestamp');
        }

        return $conversations
            ->map(fn (array $conversation) => ConversationData::from($conversation)->toArray())
            ->values()
            ->toArray();
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
