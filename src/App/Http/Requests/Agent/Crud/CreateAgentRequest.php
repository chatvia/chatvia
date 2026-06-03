<?php

namespace App\Http\Requests\Agent\Crud;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Data\Account\CreateAgentData;
use Domain\Membership\Team\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CreateAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
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
