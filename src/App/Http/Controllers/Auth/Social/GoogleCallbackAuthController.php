<?php

namespace App\Http\Controllers\Auth\Social;

use App\Auth\Actions\SocialLoginAction;
use Illuminate\Http\Request;

final class GoogleCallbackAuthController
{
    public function __invoke(Request $request, SocialLoginAction $loginAction)
    {
        $loginAction->execute('google', $request);

        return to_route('dashboard');
    }
}
