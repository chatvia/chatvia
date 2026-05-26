<?php

namespace App\Http\Controllers;

use Domain\Auth\Actions\SocialLoginAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class SlackCallbackController
{
    public function __invoke(SocialLoginAction $loginAction): RedirectResponse
    {
        $user = $loginAction->execute('slack');

        if (! $user) {
            return to_route('login')->with('error', 'No account found!');
        }

        Auth::login($user);

        return to_route('dashboard');
    }
}
