<?php

namespace Domain\Connectors\Data\Mcp;

use App\Auth\Models\User;
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
