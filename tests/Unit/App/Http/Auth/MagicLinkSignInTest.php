<?php

use App\Http\Auth\MagicLinkSignIn;
use App\Identity\Workspace\Actions\Account\DeactivateAccountAction;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * What an emailed sign-in link does once opened: the same account rules as
 * the password login apply, so a link sent before the account was switched
 * off no longer signs it in.
 */
it('sends a deactivated account to the login page instead of signing it in', function (): void {
    $user = User::factory()->create();
    $signIn = new MagicLinkSignIn($user->id, route('dashboard'));
    app(DeactivateAccountAction::class)->execute($user, 'blocked by admin');

    $response = $signIn->run();

    expect($response->getTargetUrl())->toBe(route('login'))
        ->and(Auth::guard('web')->check())->toBeFalse();
});
