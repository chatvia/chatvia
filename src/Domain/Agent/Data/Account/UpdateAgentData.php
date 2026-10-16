<?php

declare(strict_types=1);

namespace Domain\Agent\Data\Account;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * Inputs for `UpdateAgentAction::execute()`.
 *
 * A field left out of the partial update arrives as `Optional` and is left
 * alone, separately from its value being null: clients can clear a field by
 * sending `null`, but absence means "leave this alone."
 *
 * `prompt_id` and `system_instructions` are mutually exclusive at the
 * agent level — providing one clears the other. The FormRequest validates
 * with `prohibits:system_instructions` so only one branch is ever true.
 */
class UpdateAgentData extends Data
{
    public function __construct(
        public Agent $agent,
        public User $actor,
        public string|Optional $name,
        public string|Optional|null $slug,
        public string|Optional|null $promptExternalId,
        public string|Optional|null $systemInstructions,
    ) {}
}
