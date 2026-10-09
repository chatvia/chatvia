<?php

namespace App\Http\Requests\Agent\Crud;

use Domain\Agent\Data\Account\RenameAgentData;
use Domain\Agent\Models\Agent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a new name and subdomain for an agent. The name is unique per
 * workspace owner and the subdomain is unique across the platform, because it
 * becomes part of the agent's public chat URL.
 */
class RenameAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is enforced by route middleware: `can.access.agent`
        // (tenant + per-agent access) and `agent.permission:agent.setup`
        // (renaming is part of agent setup). Owners pass both; a teammate needs
        // the agent.setup grant for an agent their role can access.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Agent $agent */
        $agent = $this->route('agent');

        return [
            'name' => [
                'required', 'string', 'max:255',
                // Scope uniqueness to the agent's OWNER (agents belong to the
                // workspace owner), not the acting user — a teammate renaming on
                // the owner's behalf must still collide-check against the owner.
                Rule::unique('agents', 'name')
                    ->ignore($agent->id)
                    ->where('user_id', $agent->user_id),
            ],
            'sub_domain' => [
                'required', 'string', 'min:3', 'max:63',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('agents', 'sub_domain')->ignore($agent->id),
            ],
        ];
    }

    public function toData(): RenameAgentData
    {
        return new RenameAgentData(
            name: $this->string('name')->toString(),
            sub_domain: $this->filled('sub_domain') ? $this->string('sub_domain')->toString() : null,
        );
    }
}
