<?php

declare(strict_types=1);

namespace Domain\Notification\Queries;

use App\Identity\Workspace\Models\User;
use Domain\Notification\Enums\NotificationFilter;
use Domain\Notification\Models\UserNotification;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * One page of a person's notifications, newest activity first, for the bell
 * and the Notifications page. Pages by cursor, so a notification arriving
 * while someone scrolls does not repeat a row on the next page.
 */
final readonly class NotificationFeedQuery
{
    public const int PER_PAGE = 20;

    /**
     * @return CursorPaginator<int, UserNotification>
     */
    public function page(User $user, NotificationFilter $filter, ?string $cursor = null): CursorPaginator
    {
        return $user->userNotifications()
            ->when($filter === NotificationFilter::Unread, fn ($query) => $query->unread())
            ->latestActivity()
            ->cursorPaginate(self::PER_PAGE, ['*'], 'cursor', $cursor);
    }
}
