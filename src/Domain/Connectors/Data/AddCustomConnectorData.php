<?php

namespace Domain\Connectors\Data;

use App\Auth\Models\User;
use Spatie\LaravelData\Data;

/**
 * Account-level custom MCP connector payload. The controller branches on
 * `auth_strategy === 'oauth'` and either kicks off `McpOAuthFlow` or hands
 * this DTO (reshaped as `ProvisionCustomMcpServerData`) to the provisioning
 * action — so the DTO carries every field both branches need.
 */
class AddCustomConnectorData extends Data
{
    public function __construct(
        public User $user,
        public string $name,
        public string $url,
        public string $auth_strategy,
        public ?string $token = null,
    ) {}
}
