<?php

namespace App\Http\Requests\Team;

use App\Workspace\Models\User;
use App\Team\Models\Invitation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class AcceptInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $invitation = $this->route('invitation');

        if (! $invitation instanceof Invitation) {
            return [];
        }

        $existingUser = User::where('email', $invitation->email)->first();

        if ($existingUser) {
            return [
                'password' => ['required', 'string'],
            ];
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'policy' => ['required', 'accepted'],
            'terms' => ['required', 'accepted'],
        ];
    }
}
