<?php

namespace Domain\Agents\Data\Account;

use Spatie\LaravelData\Data;

/**
 * Inputs for `POST /api/v1/agents`. The team + prompt are resolved by external
 * id inside the action.
 */
class CreateApiAgentData extends Data
{
    public function __construct(
        public string $name,
        public ?string $team_id = null,
        public ?string $prompt_id = null,
        public ?string $system_instructions = null,
        /** @var array<string, mixed>|null */
        public ?array $metadata = null,
    ) {}
}
