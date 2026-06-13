<?php

namespace App\Http\Requests\Agent\Chatter;

use Domain\Agent\Models\Agent;
use Domain\Membership\Agent\Data\InviteAgentChatterData;
use Illuminate\Foundation\Http\FormRequest;

class InviteAgentChatterRequest extends FormRequest
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

    public function toData(Agent $agent): InviteAgentChatterData
    {
        return new InviteAgentChatterData(
            agent: $agent,
            email: $this->email(),
        );
    }
}
