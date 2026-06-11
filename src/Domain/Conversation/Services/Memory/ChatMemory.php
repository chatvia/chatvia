<?php

namespace Domain\Conversation\Services\Memory;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Memory\ConversationData;
use Domain\Conversation\Data\Memory\MessageData;
use Domain\Conversation\Data\Messages\ChatInputPayload;
use Domain\Conversation\Data\Messages\ChatOutputPayload;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Jobs\StoreMessageJob;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * Manages chat memory operations including conversations, messages, and knowledge sources.
 *
 * This class acts as a facade that orchestrates the different managers responsible for
 * conversation state, message handling, and source tracking within chat sessions.
 */
class ChatMemory
{
    private ?string $conversationId = null;

    private ?Agent $agent = null;

    private ?string $externalSessionId = null;

    private ?ChatCache $cache = null;

    private ?ConversationManager $conversationManager = null;

    private ?MessageManager $messageManager = null;

    private ?SourceManager $sourceManager = null;

    /**
     * Set the full operating context in one call — the only entry point for
     * agent + session. Setting them individually was a foot-gun: callers had
     * to remember to set both (in the right order) or hit a runtime
     * InvalidArgumentException deep inside an operation. With one call there
     * is nothing to sequence.
     *
     * `$conversationId` is optional — listing operations (e.g. "all
     * conversations for this session") legitimately run without one.
     */
    public function withContext(Agent $agent, string $externalSessionId, ?string $conversationId = null): self
    {
        $this->agent = $agent;
        $this->externalSessionId = $externalSessionId;
        $this->conversationId = $conversationId;
        $this->resetManagers();

        return $this;
    }

    /**
     * Bind a conversation after {@see withContext()} — for callers that learn
     * the conversation id only once the agent/session context is already set
     * (e.g. right after createConversation() returns it, or after a
     * per-conversation ownership check passes).
     */
    public function setConversationId(?string $conversationId = null): self
    {
        $this->conversationId = $conversationId;

        return $this;
    }

    /**
     * Create a new conversation with the given title, originating from the
     * given surface.
     */
    public function createConversation(string $title, ConversationSource $source): string
    {
        $this->ensureInitialized();

        return $this->conversationManager->createConversation($title, $source);
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
            $messages = $this->messageManager->getLlmMessages($this->conversationId, true, 2);
        }

        $this->conversationManager->updateConversationTitleFromMessages($this->conversationId, $messages);
    }

    /**
     * Delete the current conversation and all its messages.
     */
    public function deleteConversation(?string $deletedBy = null): void
    {
        $this->requireConversationId();

        $this->ensureInitialized();

        $this->conversationManager->deleteConversation($this->conversationId, $deletedBy);
    }

    /**
     * Delete all conversations for the current session.
     */
    public function deleteAllConversations(?string $deletedBy = null): void
    {
        $this->ensureInitialized();

        $this->conversationManager->deleteAllConversations($deletedBy);
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
     * Get a specific conversation by ID.
     */
    public function getConversationById(string $conversationId): ?ConversationData
    {
        $this->ensureInitialized();

        $data = $this->conversationManager->getConversationById($conversationId);

        return $data !== null ? ConversationData::from($data) : null;
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

        // Persist to the chat log unless the owner switched logging off for this
        // agent (Setup → Settings → Chat log). The in-memory turn state above is
        // untouched, so the live conversation still works — we just never write
        // it to the database, which also skips every downstream analytic.
        if ($this->agent?->chatlogEnabled() ?? true) {
            StoreMessageJob::dispatch($this->agent, $payload);
        }

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
    public function getMessageById(string $messageId): ?MessageData
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
    public function getLlmMessages(bool $asText = false, ?int $limit = null): array|string
    {
        $this->requireConversationId();

        $this->ensureInitialized();

        return $this->messageManager->getLlmMessages($this->conversationId, $asText, $limit);
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
     * Accepts 'positive', 'negative', or null to clear.
     */
    public function feedback(string $messageId, ?string $feedback): void
    {
        $this->requireConversationId();

        $this->ensureInitialized();

        $this->messageManager->addFeedback($this->conversationId, $messageId, $feedback);
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
     * Get the currently authenticated user.
     */
    public function member(): ?User
    {
        return Auth::guard('web')->user();
    }

    /**
     * Check if a user is currently authenticated.
     */
    public function isMemberAuth(): bool
    {
        return Auth::guard('web')->check();
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
        if ($this->externalSessionId === null) {
            throw new InvalidArgumentException('Session ID must be set before initialization');
        }

        if ($this->agent === null) {
            throw new InvalidArgumentException('Agent must be set before initialization');
        }

        $this->cache = new ChatCache($this->externalSessionId, $this->agent);
        $this->conversationManager = new ConversationManager($this->cache, $this->externalSessionId);
        $this->messageManager = new MessageManager($this->cache);
        $this->sourceManager = new SourceManager($this->cache);
    }
}
