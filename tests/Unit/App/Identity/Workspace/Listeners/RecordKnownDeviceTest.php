<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Actions\Sessions\RecordKnownDeviceAction;
use App\Identity\Workspace\Listeners\RecordKnownDevice;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\UserKnownDevice;
use App\Identity\Workspace\Notifications\NewDeviceSignInNotification;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

/**
 * RecordKnownDevice remembers each (IP, User-Agent) a staff account signs in
 * from. A known device only gets its last_seen_at moved on; the first device
 * ever is stored without an email; a further new device is stored and the
 * user gets a new-device email.
 */
beforeEach(function (): void {
    Notification::fake();
    $this->user = User::factory()->create();
});

/** Sign the user in from the given IP and browser. */
function rkdSignIn(User|Chatter $user, string $ip, string $userAgent): void
{
    $request = Request::create('/login', 'POST', server: ['REMOTE_ADDR' => $ip, 'HTTP_USER_AGENT' => $userAgent]);

    new RecordKnownDevice($request, app(RecordKnownDeviceAction::class))->handle(new Login('web', $user, false));
}

it('stores the first device without sending an email', function (): void {
    rkdSignIn($this->user, '203.0.113.5', 'Firefox/130');

    $device = UserKnownDevice::query()->where('user_id', $this->user->id)->sole();

    expect($device->ip)->toBe('203.0.113.5')
        ->and($device->user_agent)->toBe('Firefox/130')
        ->and($device->fingerprint_hash)->toBe(hash('sha256', '203.0.113.5|Firefox/130'));

    Notification::assertNothingSent();
});

it('only moves last_seen_at on for a known device', function (): void {
    $this->travelTo('2026-05-01 09:00:00');
    rkdSignIn($this->user, '203.0.113.5', 'Firefox/130');

    $this->travelTo('2026-05-03 18:30:00');
    rkdSignIn($this->user, '203.0.113.5', 'Firefox/130');

    $device = UserKnownDevice::query()->where('user_id', $this->user->id)->sole();

    expect($device->last_seen_at->toDateTimeString())->toBe('2026-05-03 18:30:00');
    Notification::assertNothingSent();
});

it('stores a new device and emails the user about it', function (): void {
    rkdSignIn($this->user, '203.0.113.5', 'Firefox/130');
    rkdSignIn($this->user, '198.51.100.9', 'Safari/18');

    expect(UserKnownDevice::query()->where('user_id', $this->user->id)->count())->toBe(2);

    Notification::assertSentTo(
        $this->user,
        NewDeviceSignInNotification::class,
        fn (NewDeviceSignInNotification $notification): bool => $notification->ip === '198.51.100.9' && $notification->userAgent === 'Safari/18',
    );
    Notification::assertSentTimes(NewDeviceSignInNotification::class, 1);
});

it('ignores a chatter sign-in, even one sharing a staff account id', function (): void {
    // Chatter and staff ids come from different tables and can be equal.
    $id = sharedStaffAndChatterId();
    User::factory()->create(['id' => $id]);
    $chatter = Chatter::factory()->create(['id' => $id]);

    rkdSignIn($chatter, '203.0.113.5', 'Firefox/130');

    expect(UserKnownDevice::query()->where('user_id', $id)->exists())->toBeFalse();
});
