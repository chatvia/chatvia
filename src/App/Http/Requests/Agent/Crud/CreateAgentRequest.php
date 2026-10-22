<?php

namespace App\Http\Requests\Agent\Crud;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Data\Account\CreateAgentData;
use Domain\Agent\Models\Agent;
use Domain\Membership\Team\Models\Team;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Validates a new agent's name and builds {@see CreateAgentData} for the team
 * the signed-in user is working in.
 */
class CreateAgentRequest extends FormRequest
{
    /**
     * The agent policy decides: only the owner of the selected team may add an
     * agent to it (a not-found when no team is selected yet).
     */
    public function authorize(): Response
    {
        return Gate::inspect('create', [Agent::class, $this->selectedTeam()]);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    public function toData(): CreateAgentData
    {
        /** @var User $user */
        $user = $this->user();
        /** @var Team $team */
        $team = $this->selectedTeam();

        return new CreateAgentData(
            user: $user,
            team: $team,
            name: $this->string('name')->toString(),
        );
    }

    /** The team the user is working in, which the new agent joins. */
    private function selectedTeam(): ?Team
    {
        return $this->user()?->currentOrPrimaryTeam();
    }
}
