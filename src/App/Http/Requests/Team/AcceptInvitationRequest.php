<?php

namespace App\Http\Requests\Team;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Data\AcceptInvitationData;
use Domain\Membership\Team\Models\Invitation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Accepting a team invitation. Someone who already has an account only
 * confirms their password; a new teammate picks a name and password and
 * accepts the terms. The route itself is signed, so only the emailed link
 * reaches it.
 */
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

        if ($invitation->existingAccount() instanceof User) {
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

    public function toData(Invitation $invitation): AcceptInvitationData
    {
        return new AcceptInvitationData(
            invitation: $invitation,
            password: $this->input('password'),
            name: $this->input('name'),
        );
    }
}
