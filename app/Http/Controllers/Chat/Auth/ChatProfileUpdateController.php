<?php

namespace App\Http\Controllers\Chat\Auth;

use App\Models\Member;
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

        /** @var Member $member */
        $member = $request->user('member');

        $values = [
            'name' => $request->input('name'),
        ];

        $currentPassword = $request->input('current_password');
        $newPassword = $request->input('password');

        $validation = ['email' => $member->email, 'password' => $currentPassword];

        if ($currentPassword && ! Auth::guard('member')->validate($validation)) {
            throw ValidationException::withMessages([
                'current_password' => __('auth.failed'),
            ]);
        }

        if ($newPassword) {
            $values['password'] = Hash::make($newPassword);
        }

        $member->update(
            $values
        );
    }
}
