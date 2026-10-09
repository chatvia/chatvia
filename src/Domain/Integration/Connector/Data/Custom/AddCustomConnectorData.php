<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Data\Custom;

use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Enums\ConnectorAuthStrategy;
use Spatie\LaravelData\Data;

/**
 * A custom MCP server the workspace adds by URL. With the OAuth strategy the
 * server is connected through the OAuth redirect; with token or no auth it is
 * added and synced straight away — so the DTO carries what both paths need.
 */
class AddCustomConnectorData extends Data
{
    public function __construct(
        public User $user,
        public string $name,
        public string $url,
        public ConnectorAuthStrategy $auth_strategy,
        public ?string $token = null,
    ) {}
}
