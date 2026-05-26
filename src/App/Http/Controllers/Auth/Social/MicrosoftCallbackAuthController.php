<?php

namespace App\Http\Controllers\Auth\Social;

use Domain\Auth\Actions\SocialLoginAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class MicrosoftCallbackAuthController
{
    public function __invoke(SocialLoginAction $loginAction): RedirectResponse
    {
        $user = $loginAction->execute('microsoft');

        if (! $user) {
            return to_route('login')->with('error', 'No account found!');
        }

        Auth::login($user);

        return to_route('dashboard');
    }
}
