<?php

use App\Identity\Workspace\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

/**
 * The "Forgot password" form (ForgotPasswordController): a known email gets a
 * reset link, an unknown one gets the same answer and nothing is sent, and
 * the form takes five requests a minute.
 */
beforeEach(function (): void {
    Notification::fake();
});

it('sends a reset link to a known email and answers an unknown one the same way without sending', function (): void {
    $user = User::factory()->create(['email' => 'test@example.com']);

    $this->post(route('password.email'), ['email' => 'test@example.com'])->assertSessionHas('status');
    $known = session('status');
    Notification::assertSentTo($user, ResetPassword::class);
    Notification::fake();
    $this->post(route('password.email'), ['email' => 'nonexistent@example.com'])->assertSessionHas('status', $known);

    Notification::assertNothingSent();
});

it('takes five reset requests a minute and refuses the sixth', function (): void {
    User::factory()->create(['email' => 'test@example.com']);

    foreach (range(1, 5) as $attempt) {
        $this->post(route('password.email'), ['email' => 'test@example.com'])->assertRedirect();
    }

    $this->post(route('password.email'), ['email' => 'test@example.com'])->assertStatus(429);
});
