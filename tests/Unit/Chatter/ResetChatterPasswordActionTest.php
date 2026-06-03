<?php

use App\Identity\Chatter\Actions\ResetChatterPasswordAction;
use App\Identity\Workspace\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

it('sends a password-reset notification to a chatter with an email', function (): void {
    Notification::fake();
    $workspace = User::factory()->workspace()->create();
    $chatter = User::factory()->chatter($workspace)->create([
        'email' => 'chatter@example.test',
    ]);

    app(ResetChatterPasswordAction::class)->execute($chatter);

    Notification::assertSentTo($chatter, ResetPassword::class);
});

it('is a no-op when the chatter has no email', function (): void {
    Notification::fake();
    $workspace = User::factory()->workspace()->create();
    $chatter = User::factory()->chatter($workspace)->create(['email' => null]);

    app(ResetChatterPasswordAction::class)->execute($chatter);

    Notification::assertNothingSent();
});
