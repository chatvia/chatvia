<?php

namespace App\Http\Requests\Team;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Data\CreateTeamData;
use Domain\Membership\Team\Services\TeamAllowance;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Creating a team from the Teams page. Without a license key the workspace
 * keeps only its first team, so the request is refused (403).
 */
class CreateTeamRequest extends FormRequest
{
    public function authorize(TeamAllowance $teams): Response
    {
        /** @var User $user */
        $user = $this->user();

        return $teams->canCreate($user->workspace())
            ? Response::allow()
            : Response::deny(__('This feature is unavailable. Please contact your administrator.'));
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
            workspace: $user->workspace(),
            name: $this->string('name')->toString(),
        );
    }
}
