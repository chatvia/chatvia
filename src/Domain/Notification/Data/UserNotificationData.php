<?php

declare(strict_types=1);

namespace Domain\Notification\Data;

use Carbon\CarbonInterface;
use Domain\Notification\Enums\NotificationCategory;
use Domain\Notification\Enums\NotificationKind;
use Domain\Notification\Models\UserNotification;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One notification as the bell, the Notifications page and the live
 * broadcast show it. `count` is how many events gathered into it.
 */
#[TypeScript]
class UserNotificationData extends Data
{
    public function __construct(
        public string $id,
        public NotificationKind $kind,
        public NotificationCategory $category,
        public NotificationContentData $content,
        public int $count,
        public bool $read,
        public CarbonInterface $created_at,
        public CarbonInterface $updated_at,
    ) {}

    public static function fromModel(UserNotification $notification): self
    {
        return new self(
            id: $notification->external_id,
            kind: $notification->kind,
            category: $notification->category,
            content: $notification->content,
            count: $notification->count,
            read: $notification->read_at !== null,
            created_at: $notification->created_at,
            updated_at: $notification->updated_at,
        );
    }
}
