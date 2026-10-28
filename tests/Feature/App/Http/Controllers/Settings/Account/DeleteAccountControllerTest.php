<?php

use App\Identity\Workspace\Models\User;
use Inertia\Testing\AssertableInertia;

/**
 * The Delete account page and its final click (DeleteAccountController): the
 * page lists the workspace's agents and shows whether the emailed link was
 * opened; the account goes only once it was. Teammates never reach this page
 * (WorkspacePermissionRouteTest).
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
});

it('shows the delete page to a workspace owner with their agents', function (bool $confirmed): void {
    createAgent($this->owner, ['name' => 'Support Bot']);

    $this->actingAs($this->owner)
        ->withSession($confirmed ? ['account_deletion_confirmed_at' => now()->timestamp] : [])
        ->get(route('account.delete'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('dashboard/account/pages/DeleteAccount')
            ->has('agents', 1)
            ->where('agents.0.name', 'Support Bot')
            ->where('deletionConfirmed', $confirmed));
})->with([
    'before the emailed link is opened' => false,
    'once the emailed link is opened' => true,
]);

it('blocks deletion while the email confirmation has not happened', function (): void {
    $this->actingAs($this->owner)
        ->from(route('account.delete'))
        ->delete(route('account.destroy'))
        ->assertRedirect(route('account.delete'))
        ->assertSessionHasErrors('confirmation');

    expect(User::query()->whereKey($this->owner->id)->exists())->toBeTrue();
});

it('deletes the account and signs out once confirmed', function (): void {
    // 204, not a redirect, so the Inertia client can do a full-page redirect
    // to the non-Inertia home page.
    $this->actingAs($this->owner)
        ->withSession(['account_deletion_confirmed_at' => now()->timestamp])
        ->delete(route('account.destroy'))
        ->assertNoContent();

    expect(User::query()->whereKey($this->owner->id)->exists())->toBeFalse()
        ->and(auth()->check())->toBeFalse();
});

it('hides every step of the deletion while owners may not delete their account', function (): void {
    config(['chatvia.account.deletion' => false]);

    $this->actingAs($this->owner)->get(route('account.delete'))->assertNotFound();
    $this->actingAs($this->owner)->post(route('account.delete.request'), ['password' => 'password'])->assertNotFound();
    $this->actingAs($this->owner)
        ->withSession(['account_deletion_confirmed_at' => now()->timestamp])
        ->delete(route('account.destroy'))
        ->assertNotFound();

    expect(User::query()->whereKey($this->owner->id)->exists())->toBeTrue();
});
