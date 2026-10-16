<?php

declare(strict_types=1);

namespace App\Http\Resources\Notification;

use Domain\Notification\Data\UserNotificationData;
use Domain\Notification\Models\UserNotification;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One page of notifications for the bell and the Notifications page: the
 * rows, the cursor of the next page (null on the last one) and the unread
 * count, so the badge is right after every load.
 */
#[TypeScript]
class NotificationFeedResourceData extends Data
{
    public function __construct(
        /** @var DataCollection<int, UserNotificationData> */
        #[DataCollectionOf(UserNotificationData::class)]
        public DataCollection $notifications,
        public ?string $next_cursor,
        public int $unread,
    ) {}

    /**
     * @param  CursorPaginator<int, UserNotification>  $page
     */
    public static function fromPage(CursorPaginator $page, int $unread): self
    {
        return new self(
            notifications: new DataCollection(
                UserNotificationData::class,
                array_map(UserNotificationData::fromModel(...), $page->items()),
            ),
            next_cursor: $page->nextCursor()?->encode(),
            unread: $unread,
        );
    }
}
