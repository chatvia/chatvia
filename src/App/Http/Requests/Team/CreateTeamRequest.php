<?php

namespace App\Http\Requests\Team;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Data\CreateTeamData;
use Illuminate\Foundation\Http\FormRequest;

class CreateTeamRequest extends FormRequest
{
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
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    public function toData(): CreateTeamData
    {
        /** @var User $user */
        $user = $this->user();

        return new CreateTeamData(
            owner: $user,
            name: $this->string('name')->toString(),
        );
    }
}
