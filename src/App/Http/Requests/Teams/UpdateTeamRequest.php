<?php

namespace App\Http\Requests\Teams;

use App\Teams\Models\Team;
use Domain\Teams\Data\UpdateTeamData;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->route('team');

        return $team instanceof Team && $this->user()?->ownsTeam($team);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    public function toData(Team $team): UpdateTeamData
    {
        return new UpdateTeamData(
            team: $team,
            name: $this->string('name')->toString(),
        );
    }
}
