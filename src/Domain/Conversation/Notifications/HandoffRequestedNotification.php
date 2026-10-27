<?php

declare(strict_types=1);

namespace Domain\Conversation\Notifications;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Notification\Data\NotificationAgentData;
use Domain\Notification\Data\NotificationContentData;
use Domain\Notification\Data\NotificationMessageData;
use Domain\Notification\Enums\NotificationKind;
use Domain\Notification\Notifications\InboxNotification;

/**
 * A visitor is waiting for a human on one of the agent's conversations. It
 * opens that conversation in the chat log, and is marked read for everyone
 * once someone takes it (its group key names the conversation).
 */
class HandoffRequestedNotification extends InboxNotification
{
    public function __construct(
        private readonly Conversation $conversation,
        private readonly Agent $agent,
    ) {}

    public static function groupKey(string $conversationExternalId): string
    {
        return "handoff:{$conversationExternalId}";
    }

    public function kind(): NotificationKind
    {
        return NotificationKind::HandoffRequested;
    }

    public function toInbox(User $notifiable): NotificationMessageData
    {
        return $this->message(
            new NotificationContentData(
                url: route('agents.logs', ['agent' => $this->agent, 'conversation_id' => $this->conversation->external_id], absolute: false),
                agent: NotificationAgentData::fromAgent($this->agent),
            ),
            self::groupKey($this->conversation->external_id),
        );
    }
}
