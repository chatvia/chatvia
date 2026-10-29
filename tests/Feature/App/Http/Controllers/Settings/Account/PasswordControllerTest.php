<?php

use App\Identity\Workspace\Actions\Account\UpdatePasswordAction;
use App\Identity\Workspace\Data\Account\UpdatePasswordData;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Changing the password on Account → Password: the session that made the
 * change stays signed in, and every other session of the account ends on its
 * next request.
 */
beforeEach(function (): void {
    $this->user = User::factory()->create(['password' => Hash::make('old-password')]);
});

/** Sign the user in the way a login does: the session remembers who it is. */
function pwcSignIn(User $user): void
{
    test()->withSession([Auth::guard('web')->getName() => $user->id])
        ->get(route('account.password.edit'))
        ->assertOk();
}

it('keeps the session that changed the password signed in', function (): void {
    pwcSignIn($this->user);

    $this->put(route('account.password.update'), [
        'current_password' => 'old-password',
        'password' => 'brand-new-password-1',
        'password_confirmation' => 'brand-new-password-1',
    ])->assertSessionHasNoErrors();

    Auth::forgetGuards();

    $this->get(route('account.password.edit'))->assertOk();

    expect(Auth::guard('web')->id())->toBe($this->user->id)
        ->and(Hash::check('brand-new-password-1', $this->user->fresh()->password))->toBeTrue();
});

it('ends a dashboard session once the password is changed somewhere else', function (): void {
    pwcSignIn($this->user);

    app(UpdatePasswordAction::class)->execute(new UpdatePasswordData(User::query()->findOrFail($this->user->id), 'brand-new-password-1'));
    Auth::forgetGuards();

    $this->get(route('account.password.edit'))->assertRedirect(route('login'));

    expect(Auth::guard('web')->check())->toBeFalse();
});
