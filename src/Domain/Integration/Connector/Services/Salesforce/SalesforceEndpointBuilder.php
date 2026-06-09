<?php

namespace Domain\Integration\Connector\Services\Salesforce;

use Domain\Integration\Connector\Data\OAuth\BringYourOwnOAuthData;

/**
 * Derives the Salesforce Hosted MCP server URL + OAuth endpoints from a
 * workspace's choices (environment, My Domain, server), pairing them with the
 * workspace's External Client App credentials. The MCP host is global
 * (api.salesforce.com); only the prod/sandbox path segment and the OAuth login
 * host vary per org.
 */
class SalesforceEndpointBuilder
{
    /** The Hosted MCP servers a workspace can connect (path suffix = server name). */
    public const SERVERS = [
        'sobject-all',
        'sobject-reads',
        'invocable-actions',
        'flows',
        'data-360',
        'prompt-builder',
        'tableau-next',
    ];

    public function build(
        string $clientId,
        ?string $clientSecret,
        string $environment,
        ?string $myDomain,
        string $server,
    ): BringYourOwnOAuthData {
        $isSandbox = $environment === 'sandbox';

        // OAuth login host: the org's My Domain if given (required for orgs that
        // enforce it), else the generic prod/sandbox login host.
        $loginHost = $myDomain !== null && trim($myDomain) !== ''
            ? rtrim(trim($myDomain), '/')
            : ($isSandbox ? 'https://test.salesforce.com' : 'https://login.salesforce.com');

        $segment = $isSandbox ? 'sandbox' : 'platform';

        return new BringYourOwnOAuthData(
            clientId: $clientId,
            clientSecret: $clientSecret !== null && trim($clientSecret) !== '' ? trim($clientSecret) : null,
            mcpUrl: "https://api.salesforce.com/platform/mcp/v1/{$segment}/{$server}",
            authServerUrl: $loginHost,
            authorizationEndpoint: $loginHost.'/services/oauth2/authorize',
            tokenEndpoint: $loginHost.'/services/oauth2/token',
        );
    }
}
