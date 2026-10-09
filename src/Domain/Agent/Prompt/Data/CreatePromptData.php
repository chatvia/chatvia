<?php

declare(strict_types=1);

namespace Domain\Agent\Prompt\Data;

use App\Identity\Workspace\Models\User;
use Spatie\LaravelData\Data;

/**
 * Input for adding a library prompt; `user` is the acting user, whose
 * workspace will own the prompt.
 */
class CreatePromptData extends Data
{
    public function __construct(
        public User $user,
        public string $name,
        public string $prompt,
    ) {}
}
