<?php

namespace Domain\Billing\Data;

use App\Identity\Workspace\Models\User;
use Spatie\LaravelData\Data;

class SubscribeData extends Data
{
    public function __construct(
        public User $user,
        public string $package,
        public bool $yearly,
    ) {}
}
