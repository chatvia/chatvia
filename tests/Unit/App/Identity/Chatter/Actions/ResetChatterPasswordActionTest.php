<?php

use App\Identity\Chatter\Actions\ResetChatterPasswordAction;
use App\Identity\Chatter\Models\Chatter;
use App\Identity\Chatter\Notifications\ChatterResetPasswordNotification;
use App\Identity\Chatter\Services\ChatterPasswordResetTokens;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * ResetChatterPasswordAction: emails a chatter a reset link to their agent's
 * hosted chat (/chat/{slug}/reset-password/…), backed by a token in
 * `chatter_password_reset_tokens` keyed by the chatter, never by email.
 */
beforeEach(function (): void {
    Notification::fake();

    $this->workspace = User::factory()->workspace()->create();
    $this->agent = createAgent($this->workspace);
});

it('emails the chatter a hosted-chat reset link backed by one hashed token, outside the staff broker', function (): void {
    $chatter = Chatter::factory()->grantedTo($this->agent)->create(['email' => 'chatter@example.test']);

    app(ResetChatterPasswordAction::class)->execute($chatter);
    app(ResetChatterPasswordAction::class)->execute($chatter);

    $rows = DB::table('chatter_password_reset_tokens')->where('chatter_id', $chatter->id)->get();
    expect($rows)->toHaveCount(1)
        ->and(DB::table('password_reset_tokens')->where('email', 'chatter@example.test')->exists())->toBeFalse();
    Notification::assertSentTo(
        $chatter,
        ChatterResetPasswordNotification::class,
        function (ChatterResetPasswordNotification $notification) use ($chatter, $rows): bool {
            $url = $notification->toMail($chatter)->actionUrl;

            return Str::contains($url, '/chat/'.$this->agent->slug.'/reset-password/'.$notification->token)
                && Str::contains($url, 'chatter='.$chatter->external_id)
                && $rows->first()->token !== $notification->token;
        },
    );
    Notification::assertSentTo(
        $chatter,
        ChatterResetPasswordNotification::class,
        fn (ChatterResetPasswordNotification $notification): bool => app(ChatterPasswordResetTokens::class)->isValid($chatter, $notification->token),
    );
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

it('sends nothing to a chatter without an email or without any agent to sign in to', function (): void {
    $withoutEmail = Chatter::factory()->grantedTo($this->agent)->create(['email' => null]);
    $withoutAgent = Chatter::factory()->ofWorkspace($this->workspace)->create(['email' => 'lonely@example.test']);

    app(ResetChatterPasswordAction::class)->execute($withoutEmail);
    app(ResetChatterPasswordAction::class)->execute($withoutAgent);

    Notification::assertNothingSent();
    expect(DB::table('chatter_password_reset_tokens')->whereIn('chatter_id', [$withoutEmail->id, $withoutAgent->id])->exists())->toBeFalse();
});
