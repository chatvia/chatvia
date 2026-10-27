<?php

declare(strict_types=1);

namespace Domain\Conversation\Notifications;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Notification\Data\NotificationAgentData;
use Domain\Notification\Data\NotificationContentData;
use Domain\Notification\Data\NotificationMessageData;
use Domain\Notification\Enums\NotificationKind;
use Domain\Notification\Notifications\InboxNotification;

/**
 * The agent's per-minute chat limit is turning messages away. It carries the
 * limit and opens the agent's rate-limit page; repeats gather per agent while
 * unread.
 */
class RateLimitExceededNotification extends InboxNotification
{
    public function __construct(
        private readonly Agent $agent,
        private readonly int $limitPerMinute,
    ) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::RateLimitExceeded;
    }

    public function toInbox(User $notifiable): NotificationMessageData
    {
        return $this->message(
            new NotificationContentData(
                url: route('agents.security.rate-limit', ['agent' => $this->agent], absolute: false),
                agent: NotificationAgentData::fromAgent($this->agent),
                detail: (string) $this->limitPerMinute,
            ),
            "rate-limit:{$this->agent->external_id}",
        );
    }
}
