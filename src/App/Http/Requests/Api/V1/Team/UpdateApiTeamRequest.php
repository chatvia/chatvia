<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Team;

use Domain\Membership\Team\Data\UpdateTeamData;
use Domain\Membership\Team\Models\Team;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A team's new name. Only the workspace owner may rename a team.
 */
class UpdateApiTeamRequest extends FormRequest
{
    /**
     * The dashboard's gate (TeamPolicy::update). Another workspace's team never
     * gets here: `workspace.scope` answers 404 first.
     */
    public function authorize(): bool
    {
        $team = $this->route('team');

        return $team instanceof Team && (bool) $this->user()?->can('update', $team);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // The team's name.
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    public function toData(Team $team): UpdateTeamData
    {
        return new UpdateTeamData(team: $team, name: $this->string('name')->toString());
    }
}
