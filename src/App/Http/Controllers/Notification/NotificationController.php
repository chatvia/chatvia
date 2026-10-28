<?php

declare(strict_types=1);

namespace App\Http\Controllers\Notification;

use App\Http\Resources\Notification\NotificationFeedResourceData;
use App\Identity\Workspace\Models\User;
use Domain\Notification\Actions\MarkNotificationReadAction;
use Domain\Notification\Enums\NotificationFilter;
use Domain\Notification\Queries\NotificationFeedQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The signed-in person's notification center: the full Notifications page
 * (the bell's popover reads {@see NotificationFeedController}), and marking
 * one notification read when it is opened. A notification is looked up
 * through its owner only, so someone else's id is a 404.
 */
class NotificationController
{
    public function index(Request $request, NotificationFeedQuery $feed): Response
    {
        /** @var User $user */
        $user = $request->user();
        $filter = NotificationFilter::tryFrom((string) $request->query('filter')) ?? NotificationFilter::All;

        return Inertia::render('dashboard/notifications/pages/Index', [
            'filter' => $filter,
            'feed' => NotificationFeedResourceData::fromPage(
                $feed->page($user, $filter),
                $user->userNotifications()->unread()->count(),
            ),
        ]);
    }

    public function update(Request $request, string $notification, MarkNotificationReadAction $markRead): HttpResponse
    {
        /** @var User $user */
        $user = $request->user();

        $markRead->execute($user->userNotifications()->where('external_id', $notification)->firstOrFail());

        return response()->noContent();
    }
}
