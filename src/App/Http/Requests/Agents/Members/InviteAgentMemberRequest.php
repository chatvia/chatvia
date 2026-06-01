<?php

namespace App\Http\Requests\Agents\Members;

use Domain\Agents\Data\Members\InviteAgentMemberData;
use Domain\Agents\Models\Agent;
use Illuminate\Foundation\Http\FormRequest;

class InviteAgentMemberRequest extends FormRequest
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
        ];
    }

    public function email(): string
    {
        return strtolower(trim((string) $this->validated('email')));
    }

    public function toData(Agent $agent): InviteAgentMemberData
    {
        return new InviteAgentMemberData(
            agent: $agent,
            email: $this->email(),
        );
    }
}
