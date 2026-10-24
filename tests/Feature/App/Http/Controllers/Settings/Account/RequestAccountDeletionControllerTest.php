<?php

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Notifications\AccountDeletionConfirmationNotification;
use Illuminate\Support\Facades\Notification;

/**
 * The first step of deleting an account (RequestAccountDeletionController):
 * the owner re-enters their password and gets a signed confirmation link by
 * email; a wrong password sends nothing.
 */
beforeEach(function (): void {
    Notification::fake();
    $this->owner = User::factory()->create();
});

it('emails a signed confirmation link when the password is correct', function (): void {
    $this->actingAs($this->owner)
        ->from(route('account.delete'))
        ->post(route('account.delete.request'), ['password' => 'password'])
        ->assertRedirect()
        ->assertSessionHas('success');

    Notification::assertSentOnDemand(AccountDeletionConfirmationNotification::class);
});

it('rejects a wrong password without emailing anything', function (): void {
    $this->actingAs($this->owner)
        ->from(route('account.delete'))
        ->post(route('account.delete.request'), ['password' => 'wrong-password'])
        ->assertRedirect(route('account.delete'))
        ->assertSessionHasErrors('password');

    Notification::assertNothingSent();
});
