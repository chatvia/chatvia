<?php

use App\Http\Requests\Settings\Connector\SalesforceConnectRequest;
use Domain\Integration\Connector\Data\Salesforce\SalesforceOrgData;
use Domain\Integration\Connector\Enums\SalesforceEnvironment;
use Domain\Integration\Connector\Services\Salesforce\SalesforceEndpointBuilder;
use Illuminate\Support\Facades\Validator;

/**
 * The builder turns a workspace's org choices into the concrete Salesforce
 * Hosted MCP URL + OAuth endpoints. The MCP host is global; only the prod/sandbox
 * path segment and the OAuth login host vary.
 */
beforeEach(fn (): SalesforceEndpointBuilder => $this->builder = new SalesforceEndpointBuilder);

it('builds production endpoints with the default login host', function (): void {
    $byo = $this->builder->build(new SalesforceOrgData('3MVG9key', null, SalesforceEnvironment::Production, null, 'sobject-all'));

    expect($byo->clientId)->toBe('3MVG9key')
        ->and($byo->clientSecret)->toBeNull()
        ->and($byo->mcpUrl)->toBe('https://api.salesforce.com/platform/mcp/v1/platform/sobject-all')
        ->and($byo->authServerUrl)->toBe('https://login.salesforce.com')
        ->and($byo->authorizationEndpoint)->toBe('https://login.salesforce.com/services/oauth2/authorize')
        ->and($byo->tokenEndpoint)->toBe('https://login.salesforce.com/services/oauth2/token');
});

it('uses the sandbox path segment and test login host for sandbox', function (): void {
    $byo = $this->builder->build(new SalesforceOrgData('key', null, SalesforceEnvironment::Sandbox, null, 'flows'));

    expect($byo->mcpUrl)->toBe('https://api.salesforce.com/platform/mcp/v1/sandbox/flows')
        ->and($byo->authServerUrl)->toBe('https://test.salesforce.com')
        ->and($byo->authorizationEndpoint)->toBe('https://test.salesforce.com/services/oauth2/authorize');
});

it('prefers a My Domain login host when supplied, trimming a trailing slash', function (): void {
    $byo = $this->builder->build(new SalesforceOrgData('key', null, SalesforceEnvironment::Production, 'https://acme.my.salesforce.com/', 'sobject-reads'));

    expect($byo->authServerUrl)->toBe('https://acme.my.salesforce.com')
        ->and($byo->authorizationEndpoint)->toBe('https://acme.my.salesforce.com/services/oauth2/authorize')
        ->and($byo->tokenEndpoint)->toBe('https://acme.my.salesforce.com/services/oauth2/token')
        // MCP host stays global; segment still follows the environment.
        ->and($byo->mcpUrl)->toBe('https://api.salesforce.com/platform/mcp/v1/platform/sobject-reads');
});

it('passes a non-empty client secret through and nulls a blank one', function (): void {
    expect($this->builder->build(new SalesforceOrgData('k', 'shhh', SalesforceEnvironment::Production, null, 'sobject-all'))->clientSecret)->toBe('shhh')
        ->and($this->builder->build(new SalesforceOrgData('k', '   ', SalesforceEnvironment::Production, null, 'sobject-all'))->clientSecret)->toBeNull();
});

it('lets the connect request accept exactly the servers the builder knows', function (): void {
    $rules = ['server' => (new SalesforceConnectRequest)->rules()['server']];

    foreach (SalesforceEndpointBuilder::SERVERS as $server) {
        expect(Validator::make(['server' => $server], $rules)->passes())->toBeTrue();
    }

    expect(Validator::make(['server' => 'not-a-server'], $rules)->fails())->toBeTrue();
});
