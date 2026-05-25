<?php

namespace App\Http\Controllers;

use App\Auth\Actions\SocialLoginAction;
use Illuminate\Http\Request;

class SlackCallbackController
{
    public function __invoke(Request $request, SocialLoginAction $loginAction)
    {
        $loginAction->execute('slack');

        return to_route('dashboard');

    }
}
