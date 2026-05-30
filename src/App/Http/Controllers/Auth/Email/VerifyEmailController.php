<?php

namespace App\Http\Controllers\Auth\Email;

use App\Auth\Models\User;
use App\Http\Controllers\Controller;
use Domain\Auth\Actions\VerifyEmailAction;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    public function __invoke(EmailVerificationRequest $request, VerifyEmailAction $verifyEmail): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $verifyEmail->execute($user);

        return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
    }
}
