<?php

namespace Domain\Membership\Team\Data;

use Domain\Membership\Team\Services\TeamService;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

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
            'permissions.*' => ['string', Rule::in(app(TeamService::class)->permissionCodes())],
        ];
    }
}
