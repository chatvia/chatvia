<?php

namespace App\Http\Controllers\Auth\Email;

use App\Auth\Models\User;
use App\Http\Controllers\Controller;
use Domain\Auth\Actions\ResendEmailVerificationAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    public function store(Request $request, ResendEmailVerificationAction $resend): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $resend->execute($user)) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        return back()->with('status', 'verification-link-sent');
    }
}
