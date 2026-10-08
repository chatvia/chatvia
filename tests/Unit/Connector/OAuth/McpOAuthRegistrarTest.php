<?php

use Domain\Integration\Connector\Models\ConnectorOAuthClient;
use Domain\Integration\Connector\Services\OAuth\McpOAuthRegistrar;
use Domain\Integration\Connector\Services\OAuth\OAuthServerMetadata;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Dynamic client registration must keep secrets only in the encrypted
 * columns — never in the plain-text `metadata` jsonb.
 */
it('stores registration secrets only in the encrypted columns', function (): void {
    Http::fake([
        'auth.example.test/register' => Http::response([
            'client_id' => 'dcr-client',
            'client_secret' => 'dcr-secret',
            'registration_access_token' => 'dcr-rat',
            'registration_client_uri' => 'https://auth.example.test/register/dcr-client',
            'client_id_issued_at' => 1700000000,
            'token_endpoint_auth_method' => 'none',
            'grant_types' => ['authorization_code', 'refresh_token'],
        ], 201),
    ]);

    $metadata = OAuthServerMetadata::fromArray('https://auth.example.test', [
        'authorization_endpoint' => 'https://auth.example.test/authorize',
        'token_endpoint' => 'https://auth.example.test/token',
        'registration_endpoint' => 'https://auth.example.test/register',
        'code_challenge_methods_supported' => ['S256'],
    ]);

    $client = app(McpOAuthRegistrar::class)->clientFor($metadata);

    expect($client->client_secret)->toBe('dcr-secret')
        ->and($client->registration_access_token)->toBe('dcr-rat')
        ->and($client->metadata)->not->toHaveKeys(['client_secret', 'registration_access_token'])
        ->and($client->metadata)->toMatchArray(['client_id_issued_at' => 1700000000, 'token_endpoint_auth_method' => 'none']);

    $raw = (string) DB::table('connector_oauth_clients')->where('id', $client->id)->value('metadata');
    expect($raw)->not->toContain('dcr-secret')->not->toContain('dcr-rat');
});

it('scrubs secrets already stored in the metadata column', function (): void {
    $client = ConnectorOAuthClient::create([
        'auth_server_url' => 'https://legacy.example.test',
        'client_id' => 'legacy-client',
        'client_secret' => 'legacy-secret',
        'token_endpoint' => 'https://legacy.example.test/token',
        'authorization_endpoint' => 'https://legacy.example.test/authorize',
        'metadata' => [
            'client_id' => 'legacy-client',
            'client_secret' => 'legacy-secret',
            'registration_access_token' => 'legacy-rat',
            'client_name' => 'ChatVia',
        ],
    ]);

    (require database_path('migrations/2026_10_08_417253_scrub_secrets_from_connector_oauth_clients_metadata.php'))->up();

    $metadata = $client->fresh()->metadata;
    expect($metadata)->not->toHaveKeys(['client_secret', 'registration_access_token'])
        ->and($metadata)->toMatchArray(['client_id' => 'legacy-client', 'client_name' => 'ChatVia'])
        // The encrypted column is untouched.
        ->and($client->fresh()->client_secret)->toBe('legacy-secret');
});

it('registers the client under the configured brand name', function (): void {
    config(['chatvia.brand.name' => 'Acme Chat']);

    Http::fake([
        'auth.example.test/register' => Http::response(['client_id' => 'dcr-client'], 201),
    ]);

    $metadata = OAuthServerMetadata::fromArray('https://auth.example.test', [
        'authorization_endpoint' => 'https://auth.example.test/authorize',
        'token_endpoint' => 'https://auth.example.test/token',
        'registration_endpoint' => 'https://auth.example.test/register',
        'code_challenge_methods_supported' => ['S256'],
    ]);

    app(McpOAuthRegistrar::class)->clientFor($metadata);

    Http::assertSent(fn ($request): bool => $request->url() === 'https://auth.example.test/register'
        && $request['client_name'] === 'Acme Chat');
});
