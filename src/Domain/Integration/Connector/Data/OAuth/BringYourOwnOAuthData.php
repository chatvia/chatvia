<?php

namespace Domain\Integration\Connector\Data\OAuth;

/**
 * A workspace-supplied OAuth client + resolved endpoints for a connector whose
 * OAuth app is registered per-org (no global app / no DCR) — e.g. a Salesforce
 * External Client App. Feeds {@see McpOAuthFlow::beginWithClient()}, which skips
 * discovery + DCR and uses these values directly.
 */
class BringYourOwnOAuthData
{
    public function __construct(
        public readonly string $clientId,
        public readonly ?string $clientSecret,
        public readonly string $mcpUrl,
        public readonly string $authServerUrl,
        public readonly string $authorizationEndpoint,
        public readonly string $tokenEndpoint,
    ) {}
}
