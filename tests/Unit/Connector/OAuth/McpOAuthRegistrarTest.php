<?php

use Domain\Integration\Connector\Data\OAuth\OAuthServerMetadata;
use Domain\Integration\Connector\Models\ConnectorOAuthClient;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Connector\Services\OAuth\McpOAuthRegistrar;
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

/**
 * A server without dynamic registration uses the OAuth app the admin set for
 * the connector (Settings → Connectors), read from config every time.
 */
function googleMetadata(): OAuthServerMetadata
{
    return OAuthServerMetadata::fromArray('https://accounts.google.com', [
        'authorization_endpoint' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'token_endpoint' => 'https://oauth2.googleapis.com/token',
        'code_challenge_methods_supported' => ['S256'],
    ]);
}

it('gives each connector its own OAuth app, even on a shared authorization server', function (): void {
    config([
        'connectors.gmail.oauth_client_id' => 'gmail-app',
        'connectors.gmail.oauth_client_secret' => 'gmail-secret',
        'connectors.google-drive.oauth_client_id' => 'drive-app',
        'connectors.google-drive.oauth_client_secret' => 'drive-secret',
    ]);
    $catalog = app(ConnectorCatalog::class);
    $registrar = app(McpOAuthRegistrar::class);

    $gmail = $registrar->clientFor(googleMetadata(), $catalog->find('gmail'));
    $drive = $registrar->clientFor(googleMetadata(), $catalog->find('google-drive'));

    expect($gmail->client_id)->toBe('gmail-app')
        ->and($drive->client_id)->toBe('drive-app')
        ->and($drive->client_secret)->toBe('drive-secret')
        ->and(ConnectorOAuthClient::query()->count())->toBe(0);
});

it('uses a rotated connector secret for the next connection', function (): void {
    config(['connectors.gmail.oauth_client_id' => 'gmail-app', 'connectors.gmail.oauth_client_secret' => 'old-secret']);
    $registrar = app(McpOAuthRegistrar::class);
    $registrar->clientFor(googleMetadata(), app(ConnectorCatalog::class)->find('gmail'));

    config(['connectors.gmail.oauth_client_secret' => 'new-secret']);

    expect($registrar->clientFor(googleMetadata(), app(ConnectorCatalog::class)->find('gmail'))->client_secret)->toBe('new-secret');
});
