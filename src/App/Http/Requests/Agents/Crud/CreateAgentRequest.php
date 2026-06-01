<?php

namespace App\Http\Requests\Agents\Crud;

use App\Auth\Models\User;
use App\Teams\Models\Team;
use Domain\Agents\Data\Account\CreateAgentData;
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
