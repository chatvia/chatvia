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
 * from (RecordKnownDeviceActionTest covers the row itself). The first device
 * ever and a known device send no email; a further new device sends the user
 * a new-device email.
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

it('sends no email when a known device signs in again', function (): void {
    rkdSignIn($this->user, '203.0.113.5', 'Firefox/130');
    rkdSignIn($this->user, '203.0.113.5', 'Firefox/130');

    expect(UserKnownDevice::query()->where('user_id', $this->user->id)->count())->toBe(1);
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
