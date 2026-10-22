<?php

namespace App\Identity\Workspace\Listeners;

use App\Identity\Workspace\Actions\Sessions\RecordKnownDeviceAction;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Notifications\NewDeviceSignInNotification;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * On every successful Login event, records the signing-in device
 * ({@see RecordKnownDeviceAction}) and sends a heads-up email when it is a
 * new device on an account that already knew another one.
 *
 * NOT queued — the listener must read IP + User-Agent from the live HTTP
 * request. A queued worker would inject a fresh CLI Request whose UA is
 * "Symfony" and whose IP is 127.0.0.1, defeating the whole feature. The
 * mail send is queued separately via ShouldQueue on the Notification.
 */
class RecordKnownDevice
{
    public function __construct(
        private readonly Request $request,
        private readonly RecordKnownDeviceAction $recordDevice,
    ) {}

    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $ip = $this->request->ip();
        $userAgent = Str::limit((string) $this->request->userAgent(), 1024, '');
        $userAgent = $userAgent !== '' ? $userAgent : null;

        if (! $this->recordDevice->execute($event->user, $ip, $userAgent)->isUnfamiliar()) {
            return;
        }

        $event->user->notify(new NewDeviceSignInNotification(
            ip: $ip,
            userAgent: $userAgent,
            signedInAt: Carbon::now(),
        ));
    }
}
