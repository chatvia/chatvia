<?php

namespace App\Services\Chat;

use App\Data\Chat\ChatInputPayload;
use App\Events\Chat\ChatHandoffEvent;
use App\Events\Chat\ChatMessageEvent;
use App\Events\Chat\ChatTypingEvent;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Support\Str;

class HandoffService
{
    /**
     * Take over a conversation from AI.
     */
    public function takeOver(ChatConversation $conversation, User $admin): bool
    {
        if (! $this->canTakeOver($conversation, $admin)) {
            return false;
        }

        $conversation->update([
            'handoff_status' => 'human',
            'handoff_user_id' => $admin->id,
            'handoff_at' => now(),
        ]);

        ChatHandoffEvent::dispatch(
            $conversation->id,
            'human',
            (string) $admin->id,
            $admin->name,
        );

        return true;
    }

    /**
     * Return conversation back to AI.
     */
    public function returnToAi(ChatConversation $conversation, User $admin): bool
    {
        if (! $this->canReturn($conversation, $admin)) {
            return false;
        }

        $conversation->update([
            'handoff_status' => 'returned',
            'handoff_user_id' => null,
            'handoff_at' => null,
        ]);

        ChatHandoffEvent::dispatch(
            $conversation->id,
            'returned',
            (string) $admin->id,
            $admin->name,
        );

        // Reset to AI mode after broadcast
        $conversation->update(['handoff_status' => 'ai']);

        return true;
    }

    /**
     * Send a message as admin in a taken-over conversation.
     */
    public function sendAdminMessage(ChatConversation $conversation, User $admin, string $content): ?ChatMessage
    {
        if (! $this->isHandedOff($conversation)) {
            return null;
        }

        if ($conversation->handoff_user_id !== $admin->id) {
            return null;
        }

        $session = $conversation->session;
        $agent = $session?->agent;

        if (! $agent) {
            return null;
        }

        $messageId = (string) Str::ulid();

        // Save to memory cache via ChatMemory
        $chatMemory = app(\App\Services\Memory\ChatMemory::class);
        $chatMemory
            ->setAgent($agent)
            ->setExternalSessionId($session->id)
            ->setConversationId($conversation->id)
            ->addMessage(
                role: 'agent',
                message: $content,
                messageId: $messageId,
                append: [
                    'admin_id' => $admin->id,
                    'admin_name' => $admin->name,
                ]
            );

        // Save to database with agent role and metadata
        $message = ChatMessage::create([
            'id' => $messageId,
            'chat_conversation_id' => $conversation->id,
            'content' => $content,
            'role' => 'agent',
            'metadata' => [
                'admin_id' => $admin->id,
                'admin_name' => $admin->name,
            ],
        ]);

        // Update conversation activity
        $conversation->update(['last_activity_at' => now()]);
        $session->update(['last_activity_at' => now()]);

        // Broadcast the message
        ChatMessageEvent::dispatch(
            $conversation->id,
            $messageId,
            $content,
            'agent',
            $admin->name,
            (string) $admin->id,
        );

        return $message;
    }

    /**
     * Save and broadcast a user message during handoff mode.
     */
    public function sendUserMessage(
        ChatConversation $conversation,
        string $content,
        ?string $model = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $referrerUrl = null,
        ?string $pageUrl = null,
        ?string $locale = null,
    ): ?string {
        if (! $this->isHandedOff($conversation)) {
            return null;
        }

        $messageId = (string) Str::ulid();
        $session = $conversation->session;
        $agent = $session?->agent;

        if (! $agent) {
            return null;
        }

        // Create payload for the message
        $payload = new ChatInputPayload(
            external_session_id: $session->external_id,
            external_conversation_id: $conversation->external_id,
            external_message_id: $messageId,
            model: $model ?? $agent->llms()->first()?->external_name ?? 'unknown',
            message: $content,
            sent_at: now(),
            ip_address: $ipAddress,
            user_agent: $userAgent,
            referrer_url: $referrerUrl,
            page_url: $pageUrl,
            locale: $locale,
            source: 'handoff',
        );

        // Use ChatMemory to save both to memory (cache) and dispatch persistence job
        $chatMemory = app(\App\Services\Memory\ChatMemory::class);
        $chatMemory
            ->setAgent($agent)
            ->setExternalSessionId($session->id)
            ->setConversationId($conversation->id)
            ->addMessageViaPayload($payload);

        // Get user name for broadcast
        $userName = $this->getUserName($conversation) ?? 'Visitor';

        // Broadcast the message to admin
        ChatMessageEvent::dispatch(
            $conversation->id,
            $messageId,
            $content,
            'user',
            $userName,
            $session->id,
        );

        return $messageId;
    }

    /**
     * Broadcast typing indicator.
     */
    public function broadcastTyping(
        string $conversationId,
        bool $isTyping,
        string $typerType,
        ?string $typerName = null,
        ?string $typerId = null
    ): void {
        ChatTypingEvent::dispatch(
            $conversationId,
            $isTyping,
            $typerType,
            $typerName,
            $typerId,
        );
    }

    /**
     * Check if a conversation is currently handed off to a human.
     */
    public function isHandedOff(ChatConversation $conversation): bool
    {
        return $conversation->handoff_status === 'human';
    }

    /**
     * Check if AI should respond to this conversation.
     */
    public function shouldAiRespond(ChatConversation $conversation): bool
    {
        return in_array($conversation->handoff_status, ['ai', 'returned', null]);
    }

    /**
     * Check if admin can take over this conversation.
     */
    public function canTakeOver(ChatConversation $conversation, User $admin): bool
    {
        // Already handed off to someone else
        if ($this->isHandedOff($conversation) && $conversation->handoff_user_id !== $admin->id) {
            return false;
        }

        // User must have access to the agent (owner or team member with permissions)
        $agent = $conversation->session?->agent;

        if (! $agent) {
            return false;
        }

        return \Illuminate\Support\Facades\Gate::forUser($admin)->allows('access-agent', $agent);
    }

    /**
     * Check if admin can return this conversation to AI.
     */
    public function canReturn(ChatConversation $conversation, User $admin): bool
    {
        if (! $this->isHandedOff($conversation)) {
            return false;
        }

        // Only the admin who took over can return it
        return $conversation->handoff_user_id === $admin->id;
    }

    /**
     * Get the user name for a conversation (from session member or lead).
     */
    public function getUserName(ChatConversation $conversation): ?string
    {
        // Check lead first
        $lead = $conversation->lead;
        if ($lead && $lead->name) {
            return $lead->name;
        }

        // Check member
        $member = $conversation->session?->member;
        if ($member && $member->name) {
            return $member->name;
        }

        return null;
    }

    /**
     * Get the user email for a conversation.
     */
    public function getUserEmail(ChatConversation $conversation): ?string
    {
        $lead = $conversation->lead;
        if ($lead && $lead->email) {
            return $lead->email;
        }

        $member = $conversation->session?->member;
        if ($member && $member->email) {
            return $member->email;
        }

        return null;
    }
}
