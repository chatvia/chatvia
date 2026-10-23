<?php

/**
 * ResetChatterPasswordAction: emails a chatter a reset link to their agent's
 * hosted chat (/chat/{slug}/reset-password/…), backed by a token in
 * `chatter_password_reset_tokens` keyed by the chatter, never by email.
 */

use App\Identity\Chatter\Actions\ResetChatterPasswordAction;
use App\Identity\Chatter\Models\Chatter;
use App\Identity\Chatter\Notifications\ChatterResetPasswordNotification;
use App\Identity\Chatter\Services\ChatterPasswordResetTokens;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();

    $this->workspace = User::factory()->workspace()->create();
    $this->agent = createAgent($this->workspace);
});

it('sends the chatter a reset link to the agent hosted chat', function (): void {
    $chatter = Chatter::factory()->grantedTo($this->agent)->create(['email' => 'chatter@example.test']);

    app(ResetChatterPasswordAction::class)->execute($chatter);

    Notification::assertSentTo(
        $chatter,
        ChatterResetPasswordNotification::class,
        function (ChatterResetPasswordNotification $notification) use ($chatter): bool {
            $url = $notification->toMail($chatter)->actionUrl;

            return str_contains($url, '/chat/'.$this->agent->slug.'/reset-password/'.$notification->token)
                && str_contains($url, 'chatter='.$chatter->external_id);
        },
    );
});

it('stores a hashed token for the chatter that matches the emailed one', function (): void {
    $chatter = Chatter::factory()->grantedTo($this->agent)->create(['email' => 'chatter@example.test']);

    app(ResetChatterPasswordAction::class)->execute($chatter);

    $row = DB::table('chatter_password_reset_tokens')->where('chatter_id', $chatter->id)->first();
    expect($row)->not->toBeNull();

    Notification::assertSentTo(
        $chatter,
        ChatterResetPasswordNotification::class,
        fn (ChatterResetPasswordNotification $notification): bool => $row->token !== $notification->token
            && app(ChatterPasswordResetTokens::class)->isValid($chatter, $notification->token),
    );
});

it('keeps one token per chatter when a reset is requested twice', function (): void {
    $chatter = Chatter::factory()->grantedTo($this->agent)->create(['email' => 'chatter@example.test']);

    app(ResetChatterPasswordAction::class)->execute($chatter);
    app(ResetChatterPasswordAction::class)->execute($chatter);

    expect(DB::table('chatter_password_reset_tokens')->where('chatter_id', $chatter->id)->count())->toBe(1);
});

it('does not use the staff password broker', function (): void {
    $chatter = Chatter::factory()->grantedTo($this->agent)->create(['email' => 'chatter@example.test']);

    app(ResetChatterPasswordAction::class)->execute($chatter);

    expect(DB::table('password_reset_tokens')->where('email', 'chatter@example.test')->exists())->toBeFalse();
});

it('emails only this chatter, not a staff account or another workspace chatter with the same email', function (): void {
    $chatter = Chatter::factory()->grantedTo($this->agent)->create(['email' => 'shared@example.test']);
    $staff = User::factory()->employee($this->workspace)->create(['email' => 'shared@example.test']);
    $elsewhere = Chatter::factory()->grantedTo(createAgent(User::factory()->workspace()->create()))
        ->create(['email' => 'shared@example.test']);

    app(ResetChatterPasswordAction::class)->execute($chatter);

    Notification::assertSentTo($chatter, ChatterResetPasswordNotification::class);
    Notification::assertNotSentTo($staff, ChatterResetPasswordNotification::class);
    Notification::assertNotSentTo($elsewhere, ChatterResetPasswordNotification::class);
    expect(DB::table('chatter_password_reset_tokens')->where('chatter_id', $elsewhere->id)->exists())->toBeFalse();
});

it('is a no-op when the chatter has no email', function (): void {
    $chatter = Chatter::factory()->grantedTo($this->agent)->create(['email' => null]);

    app(ResetChatterPasswordAction::class)->execute($chatter);

    Notification::assertNothingSent();
    expect(DB::table('chatter_password_reset_tokens')->where('chatter_id', $chatter->id)->exists())->toBeFalse();
});

it('sends nothing to a chatter without any agent, who could not sign in anywhere', function (): void {
    $chatter = Chatter::factory()->ofWorkspace($this->workspace)->create(['email' => 'lonely@example.test']);

    app(ResetChatterPasswordAction::class)->execute($chatter);

    Notification::assertNothingSent();
    expect(DB::table('chatter_password_reset_tokens')->where('chatter_id', $chatter->id)->exists())->toBeFalse();
});
