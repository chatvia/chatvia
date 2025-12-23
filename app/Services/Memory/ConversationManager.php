<?php

namespace App\Services\Memory;

use App\Models\ChatConversation;
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

        $conversations[] = [
            'id' => $conversationId,
            'title' => $title,
            'timestamp' => now()->timestamp,
            'visible' => false,
        ];

        $this->saveSessionData($conversations);

        return $conversationId;
    }

    public function updateConversationTitle(string $conversationId, string $title): void
    {
        $conversations = $this->getConversationsFromSession();

        foreach ($conversations as &$conversation) {
            if ($conversation['id'] === $conversationId) {
                $conversation['title'] = Str::of($title)->replace('"', '')->toString();
                $conversation['visible'] = true;
            }
        }

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
            $conversations = $conversations->filter(fn ($conversation) => $conversation['visible'] === true);
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
