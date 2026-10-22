<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Agent;

use App\Http\Requests\Api\V1\CursorPageRequest;
use App\Identity\Workspace\Models\User;
use Dedoc\Scramble\Attributes\QueryParameter;
use Domain\Membership\Team\Models\Team;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

/**
 * The paging parameters and the optional `team_id` filter of the public "list
 * agents" endpoint. The team must be one of the caller's workspace teams.
 */
#[QueryParameter('per_page', description: 'How many agents to return (1 to 100).', type: 'int', default: self::DEFAULT_PER_PAGE)]
#[QueryParameter('cursor', description: 'The `next_cursor` of the previous page. Leave it out for the first page.', type: 'string')]
class ListApiAgentsRequest extends CursorPageRequest
{
    private ?Team $team = null;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
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

                $this->team = Str::isUuid($teamId)
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
