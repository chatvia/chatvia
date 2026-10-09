<?php

declare(strict_types=1);

namespace Domain\Channel\Teams\Data;

use Spatie\LaravelData\Data;

/**
 * The credentials of a workspace's Azure bot: the Microsoft App ID, a client
 * secret from its app registration, and the Entra tenant id for single-tenant
 * bots (null for multi-tenant ones).
 */
class ConnectTeamsData extends Data
{
    public function __construct(
        public string $appId,
        public string $appSecret,
        public ?string $tenantId = null,
    ) {}
}
