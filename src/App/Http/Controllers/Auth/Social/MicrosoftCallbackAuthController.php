<?php

namespace App\Http\Controllers\Auth\Social;

use App\Auth\Actions\SocialLoginAction;
use Illuminate\Http\Request;

class MicrosoftCallbackAuthController
{
    public function __invoke(Request $request, SocialLoginAction $loginAction)
    {
        $loginAction->execute('microsoft');

        return to_route('dashboard');
    }
}
