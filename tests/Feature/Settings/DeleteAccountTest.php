<?php

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Notifications\AccountDeletionConfirmationNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->workspace = User::factory()->create();
});

function deletionConfirmUrl(User $user, ?Closure $tamper = null): string
{
    $url = URL::temporarySignedRoute(
        'account.delete.confirm',
        now()->addMinutes(30),
        ['userId' => $user->id],
    );

    return $tamper instanceof Closure ? $tamper($url) : $url;
}

it('shows the delete page to a workspace owner with their agents and subscription state', function (): void {
    createAgent($this->workspace, ['name' => 'Support Bot']);

    $this->actingAs($this->workspace)
        ->get(route('account.delete'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->component('account/DeleteAccount')
                ->has('agents', 1)
                ->where('agents.0.name', 'Support Bot')
                ->missing('hasSubscription')
                ->where('deletionConfirmed', false),
        );
});

it('forbids an employee from opening the delete page', function (): void {
    $employee = User::factory()->employee($this->workspace)->create();

    $this->actingAs($employee)
        ->get(route('account.delete'))
        ->assertForbidden();
});

it('emails a signed confirmation link when the password is correct', function (): void {
    Notification::fake();

    $this->actingAs($this->workspace)
        ->from(route('account.delete'))
        ->post(route('account.delete.request'), ['password' => 'password'])
        ->assertRedirect()
        ->assertSessionHas('success');

    Notification::assertSentOnDemand(AccountDeletionConfirmationNotification::class);
});

it('rejects a wrong password without emailing anything', function (): void {
    Notification::fake();

    $this->actingAs($this->workspace)
        ->from(route('account.delete'))
        ->post(route('account.delete.request'), ['password' => 'wrong-password'])
        ->assertRedirect(route('account.delete'))
        ->assertSessionHasErrors('password');

    Notification::assertNothingSent();
});

it('forbids an employee from requesting account deletion', function (): void {
    $employee = User::factory()->employee($this->workspace)->create();

    $this->actingAs($employee)
        ->post(route('account.delete.request'), ['password' => 'password'])
        ->assertForbidden();
});

it('arms the session and redirects when a valid signed link is opened', function (): void {
    $this->actingAs($this->workspace)
        ->get(deletionConfirmUrl($this->workspace))
        ->assertRedirect(route('account.delete'))
        ->assertSessionHas('account_deletion_confirmed_at');
});

it('flips the page into the confirmed state after opening the link', function (): void {
    $this->actingAs($this->workspace)->get(deletionConfirmUrl($this->workspace));

    $this->actingAs($this->workspace)
        ->get(route('account.delete'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('deletionConfirmed', true));
});

it('rejects an unsigned confirmation link', function (): void {
    $this->actingAs($this->workspace)
        ->get(route('account.delete.confirm', ['userId' => $this->workspace->id]))
        ->assertForbidden();
});

it('rejects a tampered confirmation link', function (): void {
    $tampered = deletionConfirmUrl($this->workspace, fn (string $url): string => $url.'x');

    $this->actingAs($this->workspace)
        ->get($tampered)
        ->assertForbidden();
});

it('rejects a confirmation link minted for a different user', function (): void {
    $other = User::factory()->create();

    // A perfectly valid signature, but for someone else's id — the controller
    // must still refuse it for the authenticated owner.
    $this->actingAs($this->workspace)
        ->get(deletionConfirmUrl($other))
        ->assertForbidden();
});

it('blocks deletion when the email confirmation has not happened', function (): void {
    $this->actingAs($this->workspace)
        ->from(route('account.delete'))
        ->delete(route('account.destroy'))
        ->assertRedirect(route('account.delete'))
        ->assertSessionHasErrors('confirmation');

    expect(User::whereKey($this->workspace->id)->exists())->toBeTrue();
});

it('deletes the account and logs out once confirmed', function (): void {
    // Returns 204 (not a redirect) so the Inertia client can do a full-page native
    // redirect to the non-Inertia home page — see DeleteUser.vue.
    $this->actingAs($this->workspace)
        ->withSession(['account_deletion_confirmed_at' => now()->timestamp])
        ->delete(route('account.destroy'))
        ->assertNoContent();

    expect(User::whereKey($this->workspace->id)->exists())->toBeFalse();
    $this->assertGuest();
});

it('forbids an employee from deleting the account', function (): void {
    $employee = User::factory()->employee($this->workspace)->create();

    $this->actingAs($employee)
        ->withSession(['account_deletion_confirmed_at' => now()->timestamp])
        ->delete(route('account.destroy'))
        ->assertForbidden();
});
