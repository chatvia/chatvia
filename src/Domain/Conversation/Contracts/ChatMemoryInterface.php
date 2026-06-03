<?php

namespace Domain\Conversation\Contracts;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Memory\ConversationData;
use Domain\Conversation\Data\Memory\MessageData;
use Domain\Conversation\Data\Messages\ChatInputPayload;
use Domain\Conversation\Data\Messages\ChatOutputPayload;
use Domain\Conversation\Enums\ConversationSource;

/**
 * Interface for chat memory operations, defining the contract for conversation management,
 * message handling, and source tracking in the chat system.
 */
interface ChatMemoryInterface
{
    /**
     * Configuration methods
     */
    public function setConversationId(?string $conversationId): self;

    public function setAgent(Agent $agent): self;

    public function setExternalSessionId(string $sessionId): self;

    /**
     * Conversation management
     */
    public function createConversation(string $title, ConversationSource $source): string;

    public function updateConversationTitle(string $title): void;

    public function updateConversationTitleFromMessages(array|string $messages = []): void;

    public function deleteConversation(): void;

    public function deleteAllConversations(): void;

    public function conversationExists(): bool;

    public function conversationExist(): bool;

    public function getConversationById(string $conversationId): ?ConversationData;

    public function getConversationsForCurrentSession(bool $onlyVisible = false, bool $orderByDate = false): array;

    /**
     * Message management
     */
    public function addMessage(string $role, string $message, ?string $messageId = null, ?string $model = null, string $type = 'text', array $append = []): string;

    public function addMessageViaPayload(ChatInputPayload|ChatOutputPayload $payload): string;

    public function updateMessage(string $messageId, array $values): void;

    public function deleteMessage(string $messageId): void;

    public function removeMessage(string $messageId): void;

    public function getMessageById(string $messageId): ?MessageData;

    public function getMessagesForConversation(bool $includeLlmMessages = false): array;

    public function getLlmMessages(bool $asText = false): array|string;

    public function getLastMessages(int $number = 3): array;

    public function feedback(string $messageId, ?string $feedback): void;

    /**
     * Source management
     */
    public function storeSourcesForMessage(string $messageId, array $sources): void;

    public function getSources(): array;

    /**
     * Authentication
     */
    public function member(): ?User;

    public function isMemberAuth(): bool;
}
