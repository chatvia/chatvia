<?php

namespace App\Http\Controllers\Auth\Password;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendPasswordResetLinkRequest;
use Domain\Auth\Actions\SendPasswordResetLinkAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(SendPasswordResetLinkRequest $request, SendPasswordResetLinkAction $sendLink): RedirectResponse
    {
        $sendLink->execute($request->string('email')->toString());

        return back()->with('status', __('A reset link will be sent if the account exists.'));
    }
}
