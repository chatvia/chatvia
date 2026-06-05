<?php

namespace Domain\Agent\Data\Account;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Spatie\LaravelData\Data;

/**
 * Inputs for `UpdateAgentAction::execute()`.
 *
 * `changePrompt` / `changeSystemInstructions` carry whether the request
 * INCLUDED the corresponding key, separately from its value being null.
 * That distinction matters: clients can clear a field by sending `null`,
 * but absence means "leave this alone."
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
        public ?string $name,
        public ?string $sub_domain,
        public bool $changePrompt,
        public ?string $promptExternalId,
        public bool $changeSystemInstructions,
        public ?string $systemInstructions,
    ) {}
}
