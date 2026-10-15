<?php

namespace App\Http\Requests\Api\V1\Team;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Data\CreateTeamData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A new team's name. Only the workspace owner may create teams.
 */
class StoreApiTeamRequest extends FormRequest
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
            // The team's name.
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    public function toData(): CreateTeamData
    {
        /** @var User $user */
        $user = $this->user();

        return new CreateTeamData(
            workspace: $user->workspace(),
            name: $this->string('name')->toString(),
        );
    }
}
