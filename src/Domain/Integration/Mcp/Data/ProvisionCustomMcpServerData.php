<?php

namespace Domain\Integration\Mcp\Data;

use App\Identity\Workspace\Models\User;
use Spatie\LaravelData\Data;

class ProvisionCustomMcpServerData extends Data
{
    public function __construct(
        public User $user,
        public string $name,
        public string $url,
        public string $strategy,
        public ?string $token = null,
    ) {}
}
