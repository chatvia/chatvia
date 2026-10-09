<?php

namespace App\Http\Requests\Api\V1\Agent;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Data\Account\CreateApiAgentData;
use Domain\Agent\Models\Agent;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class StoreApiAgentRequest extends FormRequest
{
    /**
     * The agent policy decides: only the owner of the target team may add an
     * agent to it. An unknown `team_id` is left to validation and the create
     * action, which reject it.
     */
    public function authorize(): Response
    {
        /** @var User $user */
        $user = $this->user();

        $teamId = $this->input('team_id');

        $team = is_string($teamId) && Str::isUuid($teamId)
            ? $user->workspace()->ownedTeams()->where('external_id', $teamId)->first()
            : null;

        return $team === null ? Response::allow() : Gate::inspect('create', [Agent::class, $team]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Display name for the agent, shown in the dashboard and chat header.
            'name' => ['required', 'string', 'min:3', 'max:255'],
            // The id of the team this agent belongs to.
            'team_id' => ['required', 'string', 'uuid'],
            // System prompt that defines the agent's behaviour, tone, and rules. Cannot be combined with prompt_id.
            'system_instructions' => ['sometimes', 'nullable', 'string', 'max:10000'],
            // The id of a reusable saved prompt to use instead of system_instructions. Cannot be combined with system_instructions.
            'prompt_id' => ['sometimes', 'nullable', 'string', 'uuid', 'prohibits:system_instructions'],
            // Arbitrary key/value pairs to attach to the agent — for example your own reference ids.
            'metadata' => ['sometimes', 'nullable', 'array'],
            // Whether the agent's chat is publicly reachable without a sign-in link.
            'is_public' => ['required', 'boolean'],
        ];
    }

    public function toData(): CreateApiAgentData
    {
        return CreateApiAgentData::from($this->validated());
    }
}
