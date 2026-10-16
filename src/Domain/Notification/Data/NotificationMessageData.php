<?php

declare(strict_types=1);

namespace Domain\Notification\Data;

use Domain\Notification\Enums\NotificationKind;
use Spatie\LaravelData\Data;

/**
 * One notification as a sender hands it to the inbox: its kind, its content
 * and, when it may gather with others, the key it gathers under while unread.
 */
class NotificationMessageData extends Data
{
    public function __construct(
        public NotificationKind $kind,
        public NotificationContentData $content,
        public ?string $groupKey = null,
    ) {}
}
