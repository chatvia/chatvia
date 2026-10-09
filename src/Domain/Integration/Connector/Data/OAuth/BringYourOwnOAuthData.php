<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Data\OAuth;

use Domain\Integration\Connector\Actions\OAuth\BeginBringYourOwnClientOAuthAction;

/**
 * A workspace-supplied OAuth client + resolved endpoints for a connector whose
 * OAuth app is registered per-org (no global app / no DCR) — e.g. a Salesforce
 * External Client App. Feeds {@see BeginBringYourOwnClientOAuthAction}, which
 * skips discovery + DCR and uses these values directly.
 */
final readonly class BringYourOwnOAuthData
{
    public function __construct(
        public string $clientId,
        public ?string $clientSecret,
        public string $mcpUrl,
        public string $authServerUrl,
        public string $authorizationEndpoint,
        public string $tokenEndpoint,
    ) {}
}
