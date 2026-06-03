<?php

namespace App\Http\Controllers\Auth\Registration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterUserRequest;
use App\Identity\Workspace\Actions\Account\RegisterUserAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
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
