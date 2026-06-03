<?php

namespace App\Http\Requests\Agent\Crud;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Data\Account\RenameAgentData;
use Domain\Agent\Models\Agent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RenameAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();
        /** @var Agent $agent */
        $agent = $this->route('agent');
        $team = current_team();

        if ($user === null || $team === null || ! $user->ownsTeam($team)) {
            return false;
        }

        return $user->can('update', $agent) && $agent->team_id === $team->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Agent $agent */
        $agent = $this->route('agent');

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('agents', 'name')
                    ->ignore($agent->id)
                    ->where('user_id', $this->user()->id),
            ],
            'sub_domain' => [
                'required', 'string', 'min:3', 'max:63',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('agents', 'sub_domain')->ignore($agent->id),
            ],
        ];
    }

    public function toData(): RenameAgentData
    {
        return new RenameAgentData(
            name: $this->string('name')->toString(),
            sub_domain: $this->filled('sub_domain') ? $this->string('sub_domain')->toString() : null,
        );
    }
}
