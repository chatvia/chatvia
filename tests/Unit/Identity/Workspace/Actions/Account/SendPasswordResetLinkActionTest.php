<?php

declare(strict_types=1);

use App\Identity\Workspace\Actions\Account\SendPasswordResetLinkAction;
use App\Identity\Workspace\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

/**
 * The reset link goes out for a known address and nothing distinguishes an
 * unknown one — no exception, no notification.
 */
it('sends the reset link to a known address', function (): void {
    Notification::fake();
    $user = User::factory()->create(['email' => 'owner@example.test']);

    app(SendPasswordResetLinkAction::class)->execute('owner@example.test');

    Notification::assertSentTo($user, ResetPassword::class);
});

it('quietly does nothing for an unknown address', function (): void {
    Notification::fake();

    app(SendPasswordResetLinkAction::class)->execute('nobody@example.test');

    Notification::assertNothingSent();
});
