<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Chatter;

use App\Http\Requests\Concerns\ValidatesManageableAgents;
use Domain\Agent\Models\Agent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The agents to grant a chatter. Every agent id must be one of your agents
 * that you may manage (for a teammate, an agent their team role reaches): any
 * other id fails validation, and no access is granted.
 */
class GrantApiChatterAgentsRequest extends FormRequest
{
    use ValidatesManageableAgents;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // IDs of the agents to grant the chatter access to. Grants the
            // chatter already holds are kept as they are.
            'agent_ids' => ['required', 'array', 'min:1'],
            'agent_ids.*' => ['required', 'string', $this->manageableAgentExists()],
        ];
    }

    /**
     * The workspace agents the body names.
     *
     * @return Collection<int, Agent>
     */
    public function agents(): Collection
    {
        /** @var Collection<int, Agent> */
        return $this->user()->workspace()->agents()->whereIn('external_id', (array) $this->validated('agent_ids'))->get();
    }
}
