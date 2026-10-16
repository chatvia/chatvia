<?php

declare(strict_types=1);

namespace App\Http\Controllers\Notification;

use App\Identity\Workspace\Models\User;
use Domain\Notification\Actions\MarkAllNotificationsReadAction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * "Mark all as read" in the bell and on the Notifications page.
 */
class ReadAllNotificationsController
{
    public function __invoke(Request $request, MarkAllNotificationsReadAction $markAllRead): Response
    {
        /** @var User $user */
        $user = $request->user();

        $markAllRead->execute($user);

        return response()->noContent();
    }
}
