<?php

declare(strict_types=1);

namespace App\Http\Controllers\Notification;

use App\Http\Resources\Notification\NotificationFeedResourceData;
use App\Identity\Workspace\Models\User;
use Domain\Notification\Enums\NotificationFilter;
use Domain\Notification\Queries\NotificationFeedQuery;
use Illuminate\Http\Request;

/**
 * One page of the signed-in person's notifications as JSON, for the bell's
 * popover and "Load more" (`?filter=unread`, `?cursor=`).
 */
class NotificationFeedController
{
    public function __invoke(Request $request, NotificationFeedQuery $feed): NotificationFeedResourceData
    {
        /** @var User $user */
        $user = $request->user();
        $filter = NotificationFilter::tryFrom((string) $request->query('filter')) ?? NotificationFilter::All;
        $cursor = $request->query('cursor');

        return NotificationFeedResourceData::fromPage(
            $feed->page($user, $filter, is_string($cursor) ? $cursor : null),
            $user->userNotifications()->unread()->count(),
        );
    }
}
