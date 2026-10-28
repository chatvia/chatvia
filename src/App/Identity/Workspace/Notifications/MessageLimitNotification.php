<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Notifications;

use App\Identity\Workspace\Models\MessageLimit;
use App\Identity\Workspace\Models\User;
use Domain\Notification\Data\NotificationContentData;
use Domain\Notification\Data\NotificationMessageData;
use Domain\Notification\Enums\NotificationKind;
use Domain\Notification\Notifications\InboxNotification;

/**
 * The workspace used 80% of its monthly messages, or all of them: agents stop
 * replying at the limit until next month. It carries the limit and opens the
 * dashboard, which shows the usage.
 */
class MessageLimitNotification extends InboxNotification
{
    public function __construct(
        private readonly MessageLimit $limit,
        private readonly int $percent,
    ) {}

    public function kind(): NotificationKind
    {
        return $this->percent >= 100 ? NotificationKind::MessageLimitReached : NotificationKind::MessageLimitWarning;
    }

    public function toInbox(User $notifiable): NotificationMessageData
    {
        return $this->message(new NotificationContentData(
            url: route('dashboard', absolute: false),
            subject: (string) $this->limit->monthly_limit,
            detail: (string) $this->percent,
        ));
    }
}
