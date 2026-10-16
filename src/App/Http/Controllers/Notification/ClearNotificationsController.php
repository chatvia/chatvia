<?php

declare(strict_types=1);

namespace App\Http\Controllers\Notification;

use App\Identity\Workspace\Models\User;
use Domain\Notification\Actions\ClearNotificationsAction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * "Clear all" in the bell: deletes the signed-in person's notifications.
 */
class ClearNotificationsController
{
    public function __invoke(Request $request, ClearNotificationsAction $clear): Response
    {
        /** @var User $user */
        $user = $request->user();

        $clear->execute($user);

        return response()->noContent();
    }
}
