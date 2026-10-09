<?php

namespace App\Identity\Workspace\Data\Account;

use Spatie\LaravelData\Data;

/**
 * API payload for removing a chatter's access to some agents.
 */
class RevokeChatterAgentsData extends Data
{
    public function __construct(
        /**
         * IDs of the agents to revoke the chatter's access to. Each must be an
         * agent in your workspace. The chatter keeps access to any agent not
         * listed here.
         *
         * @var list<string>
         */
        public array $agent_ids,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'agent_ids' => ['required', 'array', 'min:1'],
            'agent_ids.*' => ['required', 'string'],
        ];
    }
}
