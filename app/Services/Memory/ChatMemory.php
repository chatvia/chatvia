<?php

namespace App\Services\Memory;

use App\Contracts\ChatMemoryInterface;
use App\Data\Chat\ChatInputPayload;
use App\Data\Chat\ChatOutputPayload;
use App\Jobs\StoreChatMessageJob;
use App\Models\Agent;
use App\Models\Member;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * Manages chat memory operations including conversations, messages, and knowledge sources.
 *
 * This class acts as a facade that orchestrates the different managers responsible for
 * conversation state, message handling, and source tracking within chat sessions.
 */
class ChatMemory implements ChatMemoryInterface
{
    private ?string $conversationId = null;

    private ?Agent $agent = null;

    private ?string $sessionId = null;

    private ?ChatCache $cache = null;

    private ?ConversationManager $conversationManager = null;

    private ?MessageManager $messageManager = null;

    private ?SourceManager $sourceManager = null;

    /**
     * Set the current conversation ID for operations.
     */
    public function setConversationId(?string $conversationId = null): self
    {
        $this->conversationId = $conversationId;

        return $this;
    }

    /**
     * Set the agent context and reset cached managers.
     */
    public function setAgent(Agent $agent): self
    {
        $this->agent = $agent;
        $this->resetManagers();

        return $this;
    }

    /**
     * Set the session ID and reset cached managers.
     */
    public function setSessionId(string $sessionId): self
    {
        $this->sessionId = $sessionId;
        $this->resetManagers();

        return $this;
    }

    /**
     * Create a new conversation with the given title.
     */
    public function createConversation(string $title): string
    {
        $this->ensureInitialized();

        return $this->conversationManager->createConversation($title);
    }

    /**
     * Update the title of the current conversation.
     */
    public function updateConversationTitle(string $title): void
    {
        $this->requireConversationId();
        $this->ensureInitialized();
        $this->conversationManager->updateConversationTitle($this->conversationId, $title);
    }

    /**
     * Generate and update conversation title from messages using AI.
     */
    public function updateConversationTitleFromMessages(array|string $messages = []): void
    {
        $this->requireConversationId();
        $this->ensureInitialized();

        if (empty($messages)) {
            $messages = $this->messageManager->getLlmMessages($this->conversationId, true);
        }

        $this->conversationManager->updateConversationTitleFromMessages($this->conversationId, $messages);
    }

    /**
     * Delete the current conversation and all its messages.
     */
    public function deleteConversation(): void
    {
        $this->requireConversationId();
        $this->ensureInitialized();
        $this->conversationManager->deleteConversation($this->conversationId);
    }

    /**
     * Delete all conversations for the current session.
     */
    public function deleteAllConversations(): void
    {
        $this->ensureInitialized();
        $this->conversationManager->deleteAllConversations();
    }

    /**
     * Check if the current conversation exists.
     */
    public function conversationExists(): bool
    {
        $this->requireConversationId();
        $this->ensureInitialized();

        return $this->conversationManager->conversationExists($this->conversationId);
    }

    /**
     * Check if the current conversation exists.
     *
     * @deprecated Use conversationExists() instead
     */
    public function conversationExist(): bool
    {
        return $this->conversationExists();
    }

    /**
     * Get a specific conversation by ID.
     */
    public function getConversationById(string $conversationId): ?array
    {
        $this->ensureInitialized();

        return $this->conversationManager->getConversationById($conversationId);
    }

    /**
     * Get all conversations for the current session.
     */
    public function getConversationsForCurrentSession(bool $onlyVisible = false, bool $orderByDate = false): array
    {
        $this->ensureInitialized();

        return $this->conversationManager->getConversationsForSession($onlyVisible, $orderByDate);
    }

    /**
     * Add a message to the current conversation.
     */
    public function addMessage(string $role, string $message, ?string $messageId = null, ?string $model = null, string $type = 'text', array $append = []): string
    {
        $this->requireConversationId();
        $this->ensureInitialized();

        return $this->messageManager->addMessage(
            $this->conversationId,
            $role,
            $message,
            $messageId,
            $model,
            $type,
            $append
        );
    }

    /**
     * Add a message using a structured payload and dispatch persistence job.
     */
    public function addMessageViaPayload(ChatInputPayload|ChatOutputPayload $payload): string
    {
        $this->requireConversationId();
        $this->ensureInitialized();

        $messageId = $this->messageManager->addMessageFromPayload($this->conversationId, $payload);

        StoreChatMessageJob::dispatch($this->agent, $payload);

        return $messageId;
    }

    /**
     * Update an existing message with new values.
     */
    public function updateMessage(string $messageId, array $values): void
    {
        $this->requireConversationId();
        $this->ensureInitialized();
        $this->messageManager->updateMessage($this->conversationId, $messageId, $values);
    }

    /**
     * Delete a message and its previous message (user-assistant pair).
     */
    public function deleteMessage(string $messageId): void
    {
        $this->requireConversationId();
        $this->ensureInitialized();
        $this->messageManager->deleteMessage($this->conversationId, $messageId);
    }

    /**
     * Remove a single message from the conversation.
     */
    public function removeMessage(string $messageId): void
    {
        $this->requireConversationId();
        $this->ensureInitialized();
        $this->messageManager->removeMessage($this->conversationId, $messageId);
    }

    /**
     * Mark messages as read based on who is reading.
     * - 'user': marks agent/assistant messages as read
     * - 'admin': marks user messages as read
     */
    public function markMessagesAsRead(string $readerType): int
    {
        $this->requireConversationId();
        $this->ensureInitialized();

        return $this->messageManager->markMessagesAsRead($this->conversationId, $readerType);
    }

    /**
     * Get a specific message by its ID.
     */
    public function getMessageById(string $messageId): ?array
    {
        $this->requireConversationId();
        $this->ensureInitialized();

        return $this->messageManager->getMessageById($this->conversationId, $messageId);
    }

    /**
     * Get all messages for the current conversation.
     */
    public function getMessagesForConversation(bool $includeLlmMessages = false): array
    {
        $this->requireConversationId();
        $this->ensureInitialized();

        return $this->messageManager->getMessagesForConversation($this->conversationId, $includeLlmMessages);
    }

    /**
     * Get messages formatted for LLM consumption.
     */
    public function getLlmMessages(bool $asText = false): array|string
    {
        $this->requireConversationId();
        $this->ensureInitialized();

        return $this->messageManager->getLlmMessages($this->conversationId, $asText);
    }

    /**
     * Get the last N messages from the conversation.
     */
    public function getLastMessages(int $number = 3): array
    {
        $this->requireConversationId();
        $this->ensureInitialized();

        return $this->messageManager->getLastMessages($this->conversationId, $number);
    }

    /**
     * Add feedback (satisfaction rating) for a message.
     */
    public function feedback(string $messageId, bool $satisfied): void
    {
        $this->requireConversationId();
        $this->ensureInitialized();
        $this->messageManager->addFeedback($this->conversationId, $messageId, $satisfied);
    }

    /**
     * Store knowledge sources associated with a message.
     */
    public function storeSourcesForMessage(string $messageId, array $sources): void
    {
        $this->requireConversationId();
        $this->ensureInitialized();
        $this->sourceManager->storeSourcesForMessage($this->conversationId, $messageId, $sources);
    }

    /**
     * Get all sources for the current conversation.
     */
    public function getSources(): array
    {
        $this->requireConversationId();
        $this->ensureInitialized();

        return $this->sourceManager->getSourcesForConversation($this->conversationId);
    }

    /**
     * Get the currently authenticated member.
     */
    public function member(): ?Member
    {
        return Auth::guard('member')->user();
    }

    /**
     * Check if a member is currently authenticated.
     */
    public function isMemberAuth(): bool
    {
        return Auth::guard('member')->check();
    }

    /**
     * @deprecated Use the specific manager methods instead
     */
    public function getChatCache()
    {
        $this->ensureInitialized();

        return $this->cache->getTaggedCache();
    }

    /**
     * @deprecated Use the specific manager methods instead
     */
    public function cachePut($key, $value): void
    {
        $this->ensureInitialized();
        $this->cache->put($key, $value);
    }

    /**
     * @deprecated Use the specific manager methods instead
     */
    public function cacheGet($cacheId)
    {
        $this->ensureInitialized();

        return $this->cache->get($cacheId);
    }

    /**
     * @deprecated Use the specific manager methods instead
     */
    public function cacheHas($cacheId): bool
    {
        $this->ensureInitialized();

        return $this->cache->has($cacheId);
    }

    /**
     * @deprecated Use the specific manager methods instead
     */
    public function cacheForget($cacheId): bool
    {
        $this->ensureInitialized();

        return $this->cache->forget($cacheId);
    }

    /**
     * @deprecated Use the specific manager methods instead
     */
    public function cacheFlush(): bool
    {
        $this->ensureInitialized();

        return $this->cache->flush();
    }

    /**
     * Ensure all managers are initialized before use.
     */
    private function ensureInitialized(): void
    {
        if ($this->cache === null) {
            $this->initializeManagers();
        }
    }

    /**
     * Validate that conversation ID is set for operations that require it.
     */
    private function requireConversationId(): void
    {
        if ($this->conversationId === null) {
            throw new InvalidArgumentException('Conversation ID must be set before performing this operation');
        }
    }

    /**
     * Reset all cached managers when configuration changes.
     */
    private function resetManagers(): void
    {
        $this->cache = null;
        $this->conversationManager = null;
        $this->messageManager = null;
        $this->sourceManager = null;
    }

    /**
     * Initialize all manager instances with current configuration.
     */
    private function initializeManagers(): void
    {
        if ($this->sessionId === null) {
            throw new InvalidArgumentException('Session ID must be set before initialization');
        }

        if ($this->agent === null) {
            throw new InvalidArgumentException('Agent must be set before initialization');
        }

        $this->cache = new ChatCache($this->sessionId, $this->agent);
        $this->conversationManager = new ConversationManager($this->cache, $this->sessionId);
        $this->messageManager = new MessageManager($this->cache, $this->agent);
        $this->sourceManager = new SourceManager($this->cache);
    }
}
