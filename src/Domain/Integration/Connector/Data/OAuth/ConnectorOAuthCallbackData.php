<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Data\OAuth;

/**
 * What the provider's redirect back to us carries for a successful consent —
 * the `state` and authorization `code` — plus the browser-session secret the
 * flow was bound to when it started.
 */
final readonly class ConnectorOAuthCallbackData
{
    public function __construct(
        public string $state,
        public string $code,
        public string $sessionBinding,
    ) {}
}
