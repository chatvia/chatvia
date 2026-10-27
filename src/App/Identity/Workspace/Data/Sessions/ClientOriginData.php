<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Data\Sessions;

/**
 * Where a widget request comes from: the client's IP address and User-Agent.
 * A widget session token is pinned to the origin it was issued to, so a
 * token copied to another machine does not work there.
 */
final readonly class ClientOriginData
{
    public function __construct(
        public ?string $ip,
        public ?string $userAgent,
    ) {}
}
