<?php

namespace Domain\Integration\Connector\Data\Account;

use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Data\Catalog\ConnectorData;

/**
 * Inputs for connecting a token or no-auth catalog connector: who connects
 * it, which connector, the pasted token (token connectors only) and, for
 * presets without a fixed endpoint (`requiresUrl`), the workspace's own MCP
 * server URL.
 */
final readonly class ConnectTokenConnectorData
{
    public function __construct(
        public User $user,
        public ConnectorData $connector,
        public ?string $token = null,
        public ?string $url = null,
    ) {}
}
