<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings\Account;

use App\Http\Requests\Settings\Account\UpdateNotificationPreferencesRequest;
use App\Identity\Workspace\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The account's Notifications page: per category, whether it rings the bell,
 * shows a desktop alert and plays a sound. Saving is a single-column write,
 * so it stays here rather than behind an Action.
 */
class NotificationPreferencesController
{
    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('dashboard/account/pages/Notifications', [
            'preferences' => $user->notificationPreferences(),
        ]);
    }

    public function update(UpdateNotificationPreferencesRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->update(['notification_preferences' => $request->toData()->toArray()]);

        return back()->with('success', __('Notification settings saved.'));
    }
}
