<?php

namespace App\Auth\Listeners;

use App\Auth\Models\User;
use App\Auth\Models\UserKnownDevice;
use App\Auth\Notifications\NewDeviceSignInNotification;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * On every successful Login event, decide whether the signing-in device is
 * one we've seen before for this user. New devices get a heads-up email and
 * a fresh row; recognized devices just have their last_seen_at bumped.
 *
 * NOT queued — the listener must read IP + User-Agent from the live HTTP
 * request. A queued worker would inject a fresh CLI Request whose UA is
 * "Symfony" and whose IP is 127.0.0.1, defeating the whole feature. The
 * mail send is queued separately via ShouldQueue on the Notification.
 */
class RecordKnownDeviceListener
{
    public function __construct(private readonly Request $request) {}

    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $ip = $this->request->ip();
        $userAgent = Str::limit((string) $this->request->userAgent(), 1024, '');
        $fingerprint = hash('sha256', ($ip ?? '').'|'.$userAgent);

        $device = UserKnownDevice::query()
            ->where('user_id', $event->user->id)
            ->where('fingerprint_hash', $fingerprint)
            ->first();

        if ($device !== null) {
            $device->update(['last_seen_at' => Carbon::now()]);

            return;
        }

        UserKnownDevice::create([
            'user_id' => $event->user->id,
            'fingerprint_hash' => $fingerprint,
            'ip' => $ip,
            'user_agent' => $userAgent !== '' ? $userAgent : null,
            'last_seen_at' => Carbon::now(),
        ]);

        // First sign-in ever isn't a "new device" alert — it's the only
        // device. Skip the email when the user has no other known devices.
        $otherDevicesExist = UserKnownDevice::query()
            ->where('user_id', $event->user->id)
            ->where('fingerprint_hash', '!=', $fingerprint)
            ->exists();

        if (! $otherDevicesExist) {
            return;
        }

        $event->user->notify(new NewDeviceSignInNotification(
            ip: $ip,
            userAgent: $userAgent !== '' ? $userAgent : null,
            signedInAt: Carbon::now(),
        ));
    }
}
