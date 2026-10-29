<?php

declare(strict_types=1);

use Domain\Integration\Connector\Data\OAuth\OAuthServerMetadataData;
use Domain\Integration\Connector\Exceptions\McpOAuthException;
use Domain\Integration\Connector\Models\ConnectorOAuthClient;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Connector\Services\OAuth\McpOAuthRegistrar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Getting an OAuth client for a connector: registered once per authorization
 * server through dynamic client registration (RFC 7591), with its secrets
 * kept only in the encrypted columns, never the plain-text `metadata` jsonb.
 * A server without dynamic registration uses the OAuth app the admin set for
 * the connector (Settings → Connectors), read from config every time.
 */

/**
 * Metadata of an authorization server that supports dynamic client
 * registration (RFC 7591) at `/register`.
 */
function morDcrMetadata(): OAuthServerMetadataData
{
    return OAuthServerMetadataData::fromArray('https://auth.example.test', [
        'authorization_endpoint' => 'https://auth.example.test/authorize',
        'token_endpoint' => 'https://auth.example.test/token',
        'registration_endpoint' => 'https://auth.example.test/register',
        'code_challenge_methods_supported' => ['S256'],
    ]);
}

/** Metadata of an authorization server without dynamic registration. */
function morGoogleMetadata(): OAuthServerMetadataData
{
    return OAuthServerMetadataData::fromArray('https://accounts.google.com', [
        'authorization_endpoint' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'token_endpoint' => 'https://oauth2.googleapis.com/token',
        'code_challenge_methods_supported' => ['S256'],
    ]);
}

it('registers a client once per authorization server, keeping its secrets only in the encrypted columns', function (): void {
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
    $registrar = app(McpOAuthRegistrar::class);

    $client = $registrar->clientFor(morDcrMetadata());
    $again = $registrar->clientFor(morDcrMetadata());

    $raw = (string) DB::table('connector_oauth_clients')->where('id', $client->id)->value('metadata');
    expect($client->client_secret)->toBe('dcr-secret')
        ->and($client->registration_access_token)->toBe('dcr-rat')
        ->and($client->metadata)->not->toHaveKeys(['client_secret', 'registration_access_token'])
        ->and($client->metadata)->toMatchArray(['client_id_issued_at' => 1700000000, 'token_endpoint_auth_method' => 'none'])
        ->and($raw)->not->toContain('dcr-secret')->not->toContain('dcr-rat')
        ->and($again->is($client))->toBeTrue()
        ->and(ConnectorOAuthClient::query()->count())->toBe(1);
    Http::assertSentCount(1);
});

it('stores nothing when the registration is rejected or returns no client id', function (mixed $body, int $status, string $message): void {
    Http::fake(['auth.example.test/register' => Http::response($body, $status)]);

    expect(fn () => app(McpOAuthRegistrar::class)->clientFor(morDcrMetadata()))
        ->toThrow(McpOAuthException::class, $message);

    expect(ConnectorOAuthClient::query()->count())->toBe(0);
})->with([
    'rejected' => ['invalid_redirect_uri', 400, 'Dynamic client registration was rejected (400)'],
    'no client id' => [['client_name' => 'ChatViaAI'], 201, 'Dynamic client registration returned no client_id.'],
]);

it('uses each connector’s own OAuth app as configured now, and refuses a connector without one', function (): void {
    config([
        'connectors.oauth_apps.gmail.client_id' => 'gmail-app',
        'connectors.oauth_apps.gmail.client_secret' => 'old-secret',
        'connectors.oauth_apps.google-drive.client_id' => 'drive-app',
        'connectors.oauth_apps.google-drive.client_secret' => 'drive-secret',
    ]);
    $catalog = app(ConnectorCatalog::class);
    $registrar = app(McpOAuthRegistrar::class);

    $gmail = $registrar->clientFor(morGoogleMetadata(), $catalog->find('gmail'));
    $drive = $registrar->clientFor(morGoogleMetadata(), $catalog->find('google-drive'));
    config(['connectors.oauth_apps.gmail.client_secret' => 'new-secret']);
    $rotated = $registrar->clientFor(morGoogleMetadata(), $catalog->find('gmail'));
    config(['connectors.oauth_apps.gmail.client_id' => null]);

    expect($gmail->client_id)->toBe('gmail-app')
        ->and($drive->client_id)->toBe('drive-app')
        ->and($drive->client_secret)->toBe('drive-secret')
        ->and($rotated->client_secret)->toBe('new-secret')
        ->and(ConnectorOAuthClient::query()->count())->toBe(0)
        ->and(fn () => $registrar->clientFor(morGoogleMetadata(), $catalog->find('gmail')))
        ->toThrow(McpOAuthException::class, 'requires a pre-registered OAuth client')
        ->and(fn () => $registrar->clientFor(morGoogleMetadata()))
        ->toThrow(McpOAuthException::class, 'requires a pre-registered OAuth client');
});
