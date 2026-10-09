<?php

namespace App\Http\Requests\Agent\Crud;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Data\Account\CreateAgentData;
use Domain\Agent\Models\Agent;
use Domain\Membership\Team\Models\Team;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CreateAgentRequest extends FormRequest
{
    /**
     * The agent policy decides: only the owner of the selected team may add an
     * agent to it (a not-found when no team is selected yet).
     */
    public function authorize(): Response
    {
        return Gate::inspect('create', [Agent::class, current_team()]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
        ];
    }

    public function toData(): CreateAgentData
    {
        /** @var User $user */
        $user = $this->user();
        /** @var Team $team */
        $team = current_team();

        return new CreateAgentData(
            user: $user,
            team: $team,
            name: $this->string('name')->toString(),
        );
    }
}
