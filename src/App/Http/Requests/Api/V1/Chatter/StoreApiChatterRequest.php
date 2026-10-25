<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Chatter;

use App\Http\Requests\Concerns\ValidatesManageableAgents;
use App\Identity\Chatter\Data\CreateChatterData;
use App\Identity\Workspace\Models\Workspace;
use Domain\Agent\Models\Agent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A new chatter and the agents they may use. Every agent id must be one of
 * your agents that you may manage (for a teammate, an agent their team role
 * reaches): any other id fails validation, and no chatter is created.
 */
class StoreApiChatterRequest extends FormRequest
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
            // The chatter's name.
            'name' => ['required', 'string', 'max:255'],
            // IDs of the agents the chatter may sign in to. Provide at least one.
            'agent_ids' => ['required', 'array', 'min:1'],
            'agent_ids.*' => ['required', 'string', $this->manageableAgentExists()],
            // The chatter's login email. Unique among your chatters only
            // (checked when the chatter is created), so the answer never
            // tells whether an email is used anywhere else.
            'email' => ['required', 'email', 'max:255'],
            // The chatter's password. At least 8 characters.
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ];
    }

    public function toData(): CreateChatterData
    {
        $workspace = $this->workspace();

        /** @var Collection<int, Agent> $agents */
        $agents = $workspace->agents()->whereIn('external_id', (array) $this->validated('agent_ids'))->get();

        return new CreateChatterData(
            workspace: $workspace,
            agents: $agents,
            name: $this->string('name')->toString(),
            email: $this->string('email')->toString(),
            password: $this->string('password')->toString(),
        );
    }

    private function workspace(): Workspace
    {
        return $this->user()->workspace();
    }
}
