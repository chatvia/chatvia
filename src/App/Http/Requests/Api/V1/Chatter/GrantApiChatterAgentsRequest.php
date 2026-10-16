<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Chatter;

use App\Http\Rules\WorkspaceRule;
use Domain\Agent\Models\Agent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The agents to grant a chatter. Every agent id must be one of your agents:
 * any other id fails validation, and no access is granted.
 */
class GrantApiChatterAgentsRequest extends FormRequest
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
            // IDs of the agents to grant the chatter access to. Grants the
            // chatter already holds are kept as they are.
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
