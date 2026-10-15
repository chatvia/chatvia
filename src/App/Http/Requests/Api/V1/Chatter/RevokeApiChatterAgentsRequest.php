<?php

namespace App\Http\Requests\Api\V1\Chatter;

use App\Http\Rules\WorkspaceRule;
use Domain\Agent\Models\Agent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The agents to take away from a chatter. Every agent id must be one of your
 * agents: any other id fails validation, and no access is removed.
 */
class RevokeApiChatterAgentsRequest extends FormRequest
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
        return [
            // IDs of the agents to revoke the chatter's access to. The chatter
            // keeps access to any agent not listed here.
            'agent_ids' => ['required', 'array', 'min:1'],
            'agent_ids.*' => ['required', 'string', WorkspaceRule::exists('agents', $this->user()->workspace(), 'external_id')->withoutTrashed()],
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
