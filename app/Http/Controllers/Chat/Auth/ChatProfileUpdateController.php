<?php

namespace App\Http\Controllers\Chat\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ChatProfileUpdateController
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'current_password' => ['nullable', 'string', 'min:8'],
            'password' => ['nullable', 'required_with:current_password', 'string', 'min:8', 'confirmed'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $values = [
            'name' => $request->input('name'),
        ];

        $currentPassword = $request->input('current_password');
        $newPassword = $request->input('password');

        $validation = ['email' => $user->email, 'password' => $currentPassword];

        if ($currentPassword && ! Auth::guard('web')->validate($validation)) {
            throw ValidationException::withMessages([
                'current_password' => __('auth.failed'),
            ]);
        }

        if ($newPassword) {
            $values['password'] = Hash::make($newPassword);
        }

        $user->update(
            $values
        );
    }
}
