<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Services\Salesforce;

use Domain\Integration\Connector\Data\OAuth\BringYourOwnOAuthData;
use Domain\Integration\Connector\Data\Salesforce\SalesforceOrgData;
use Domain\Integration\Connector\Enums\SalesforceEnvironment;
use Illuminate\Support\Str;

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
    public const array SERVERS = [
        'sobject-all',
        'sobject-reads',
        'invocable-actions',
        'flows',
        'data-360',
        'prompt-builder',
        'tableau-next',
    ];

    public function build(SalesforceOrgData $org): BringYourOwnOAuthData
    {
        $isSandbox = $org->environment === SalesforceEnvironment::Sandbox;

        // OAuth login host: the org's My Domain if given (required for orgs that
        // enforce it), else the generic prod/sandbox login host.
        $loginHost = filled($org->myDomain)
            ? Str::rtrim(Str::trim($org->myDomain), '/')
            : ($isSandbox ? 'https://test.salesforce.com' : 'https://login.salesforce.com');

        $segment = $isSandbox ? 'sandbox' : 'platform';

        return new BringYourOwnOAuthData(
            clientId: $org->clientId,
            clientSecret: filled($org->clientSecret) ? Str::trim($org->clientSecret) : null,
            mcpUrl: "https://api.salesforce.com/platform/mcp/v1/{$segment}/{$org->server}",
            authServerUrl: $loginHost,
            authorizationEndpoint: $loginHost.'/services/oauth2/authorize',
            tokenEndpoint: $loginHost.'/services/oauth2/token',
        );
    }
}
