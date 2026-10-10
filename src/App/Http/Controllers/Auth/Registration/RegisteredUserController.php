<?php

namespace App\Http\Controllers\Auth\Registration;

use App\Http\Requests\Auth\RegisterUserRequest;
use App\Identity\Workspace\Actions\Account\RegisterUserAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The self-serve sign-up form (open only when `chatvia.registration_enabled`
 * is on, the "Open sign-up" option in the admin panel):
 * shows it, creates the workspace account and signs the new owner in.
 */
class RegisteredUserController
{
    public function create(): Response
    {
        return Inertia::render('auth/Register');
    }

    public function store(RegisterUserRequest $request, RegisterUserAction $registerUser): RedirectResponse
    {
        $user = $registerUser->execute($request->toData());

        Auth::login($user);

        return to_route('dashboard');
    }
}
