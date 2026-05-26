<?php

namespace App\Http\Controllers\Auth;

use App\Auth\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Domain\Auth\Actions\LoginUserAction;
use Domain\Auth\Actions\LogoutUserAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(LoginRequest $request, LoginUserAction $loginUser): RedirectResponse
    {
        $request->ensureIsNotRateLimited();

        $user = $loginUser->execute($request->toData());

        if (! $user) {
            RateLimiter::hit($request->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($request->throttleKey());

        $request->session()->regenerate();

        $user->ensureCurrentTeam();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request $request, LogoutUserAction $logoutUser): RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::user();

        Auth::guard('web')->logout();

        if ($user) {
            $logoutUser->execute($user);
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
