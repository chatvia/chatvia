<?php

namespace Domain\Membership\Team\Data;

use Domain\Membership\Team\Services\TeamPermissionCatalog;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

/**
 * Public-API request body for creating or updating a team role. Spatie Data
 * validates it from the request; the controller turns it into
 * {@see SaveTeamRoleData} for the role Actions.
 */
class RolePayloadData extends Data
{
    public function __construct(
        /**
         * The role's name.
         */
        public string $name,
        /**
         * Grant access to every agent in the team.
         */
        public bool $all_agents = false,
        /**
         * IDs of the agents this role can access (ignored when all_agents is true).
         *
         * @var list<string>
         */
        public array $agent_ids = [],
        /**
         * Permission codes to grant the role's members.
         *
         * @var list<string>
         */
        public array $permissions = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'all_agents' => ['sometimes', 'boolean'],
            'agent_ids' => ['sometimes', 'array'],
            'agent_ids.*' => ['string'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::in(app(TeamPermissionCatalog::class)->codes())],
        ];
    }
}
