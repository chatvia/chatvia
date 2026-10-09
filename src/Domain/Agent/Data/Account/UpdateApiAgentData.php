<?php

declare(strict_types=1);

namespace Domain\Agent\Data\Account;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * Inputs for `PATCH /api/v1/agents/{agent}`. Each property is `Optional` so the
 * partial update can distinguish "not present" from `null`.
 */
class UpdateApiAgentData extends Data
{
    public function __construct(
        public string|Optional $name,
        public string|null|Optional $system_instructions,
        public string|null|Optional $prompt_id,
        public array|null|Optional $metadata,
        public bool|Optional $is_public,
    ) {}
}
