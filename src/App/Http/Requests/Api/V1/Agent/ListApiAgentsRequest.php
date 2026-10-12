<?php

namespace App\Http\Requests\Api\V1\Agent;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

/**
 * The optional `team_id` filter of the public "list agents" endpoint. The
 * team must be one of the caller's workspace teams.
 */
class ListApiAgentsRequest extends FormRequest
{
    private ?Team $team = null;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            // Only list the agents of this team (its id).
            'team_id' => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->filled('team_id')) {
                    return;
                }

                /** @var User $user */
                $user = $this->user();

                $teamId = $this->query('team_id');

                $this->team = is_string($teamId) && Str::isUuid($teamId)
                    ? $user->workspace()->teams()->where('external_id', $teamId)->first()
                    : null;

                if ($this->team === null) {
                    $validator->errors()->add('team_id', __('Team not found.'));
                }
            },
        ];
    }

    /** The team to list, or null for every team the caller may see. */
    public function team(): ?Team
    {
        return $this->team;
    }
}
