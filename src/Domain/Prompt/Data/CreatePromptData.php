<?php

namespace Domain\Prompt\Data;

use App\Workspace\Models\User;
use Spatie\LaravelData\Data;

class CreatePromptData extends Data
{
    public function __construct(
        public User $user,
        public string $name,
        public string $prompt,
    ) {}
}
