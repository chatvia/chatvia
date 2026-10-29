<?php

use App\Identity\Workspace\Models\User;

/**
 * The final click on the Delete account page (DeleteAccountController): the
 * account goes, and its session ends, only once the emailed link was opened.
 * Teammates never reach this page (WorkspacePermissionRouteTest).
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
});

it('blocks deletion while the email confirmation has not happened', function (): void {
    $this->actingAs($this->owner)
        ->from(route('account.delete'))
        ->delete(route('account.destroy'))
        ->assertRedirect(route('account.delete'))
        ->assertSessionHasErrors('confirmation');

    expect(User::query()->whereKey($this->owner->id)->exists())->toBeTrue();
});

it('deletes the account and signs out once confirmed', function (): void {
    $this->actingAs($this->owner)
        ->withSession(['account_deletion_confirmed_at' => now()->timestamp])
        ->delete(route('account.destroy'))
        ->assertNoContent();

    expect(User::query()->whereKey($this->owner->id)->exists())->toBeFalse()
        ->and(auth()->check())->toBeFalse();
});
