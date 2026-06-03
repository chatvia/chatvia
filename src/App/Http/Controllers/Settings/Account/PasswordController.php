<?php

namespace App\Http\Controllers\Settings\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\Account\UpdatePasswordRequest;
use App\Identity\Workspace\Actions\Account\UpdatePasswordAction;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PasswordController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('settings/Password');
    }

    public function update(UpdatePasswordRequest $request, UpdatePasswordAction $updatePassword): RedirectResponse
    {
        $updatePassword->execute($request->toData());

        return back();
    }
}
