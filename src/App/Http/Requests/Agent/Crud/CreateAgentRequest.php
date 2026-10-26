<?php

namespace App\Http\Requests\Agent\Crud;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Data\Account\CreateAgentData;
use Domain\Agent\Exceptions\AgentLimitReachedException;
use Domain\Agent\Models\Agent;
use Domain\Agent\Services\AgentAllowance;
use Domain\Membership\Team\Models\Team;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

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

    /**
     * Without a license key a workspace holds ten agents (a free-use limit):
     * the eleventh is refused here with a clear message, before the action's
     * own check.
     *
     * @return list<\Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var User $user */
                $user = $this->user();
                $allowance = app(AgentAllowance::class);

                if (! $allowance->canCreate($user->workspace())) {
                    $validator->errors()->add('name', AgentLimitReachedException::make((int) $allowance->limit())->getMessage());
                }
            },
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
