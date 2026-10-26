<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Team;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Data\CreateTeamData;
use Domain\Membership\Team\Services\TeamAllowance;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A new team's name. Only the workspace owner may create teams, and without a
 * license key the workspace keeps only its first team (403).
 */
class StoreApiTeamRequest extends FormRequest
{
    public function authorize(TeamAllowance $teams): Response
    {
        /** @var User $user */
        $user = $this->user();

        return $teams->canCreate($user->workspace())
            ? Response::allow()
            : Response::deny(__('More teams need a ChatVia.ai license key.'));
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
