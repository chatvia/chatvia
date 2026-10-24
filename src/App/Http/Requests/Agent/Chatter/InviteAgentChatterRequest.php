<?php

declare(strict_types=1);

namespace App\Http\Requests\Agent\Chatter;

use Domain\Agent\Models\Agent;
use Domain\Membership\Agent\Data\InviteAgentChatterData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Inviting someone to chat with one agent. The email is normalised (trimmed,
 * lower-cased) before it reaches the Action.
 */
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
        return Str::lower(Str::trim((string) $this->validated('email')));
    }

    public function toData(Agent $agent): InviteAgentChatterData
    {
        return new InviteAgentChatterData(
            agent: $agent,
            email: $this->email(),
        );
    }
}
