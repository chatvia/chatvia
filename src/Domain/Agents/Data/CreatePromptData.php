<?php

namespace Domain\Agents\Data;

use App\Auth\Models\User;
use Spatie\LaravelData\Data;

class CreatePromptData extends Data
{
    public function __construct(
        public User $user,
        public string $name,
        public string $prompt,
    ) {}
}
