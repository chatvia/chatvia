<?php

namespace App\Http\Requests\Agent\Chatter;

use Domain\Membership\Agent\Data\AcceptAgentInvitationData;
use Domain\Membership\Agent\Enums\InvitationCredentialKind;
use Domain\Membership\Agent\Models\AgentChatterInvitation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Accepting an agent invitation with a password (the social path has its own
 * OAuth callback). The invitation itself is looked up from the link's token.
 */
class AcceptAgentInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:255'],
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    public function toData(AgentChatterInvitation $invitation): AcceptAgentInvitationData
    {
        return new AcceptAgentInvitationData(
            invitation: $invitation,
            name: (string) $this->validated('name'),
            kind: InvitationCredentialKind::Password,
            password: (string) $this->validated('password'),
        );
    }
}
