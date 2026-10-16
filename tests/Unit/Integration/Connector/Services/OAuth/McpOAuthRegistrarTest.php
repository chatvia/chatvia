<?php

use Domain\Integration\Connector\Data\OAuth\OAuthServerMetadata;
use Domain\Integration\Connector\Exceptions\McpOAuthException;
use Domain\Integration\Connector\Models\ConnectorOAuthClient;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Connector\Services\OAuth\McpOAuthRegistrar;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Metadata of an authorization server that supports dynamic client
 * registration (RFC 7591) at `/register`.
 */
function morDcrMetadata(): OAuthServerMetadata
{
    return OAuthServerMetadata::fromArray('https://auth.example.test', [
        'authorization_endpoint' => 'https://auth.example.test/authorize',
        'token_endpoint' => 'https://auth.example.test/token',
        'registration_endpoint' => 'https://auth.example.test/register',
        'code_challenge_methods_supported' => ['S256'],
    ]);
}

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

    $client = app(McpOAuthRegistrar::class)->clientFor(morDcrMetadata());

    expect($client->client_secret)->toBe('dcr-secret')
        ->and($client->registration_access_token)->toBe('dcr-rat')
        ->and($client->metadata)->not->toHaveKeys(['client_secret', 'registration_access_token'])
        ->and($client->metadata)->toMatchArray(['client_id_issued_at' => 1700000000, 'token_endpoint_auth_method' => 'none']);

    $raw = (string) DB::table('connector_oauth_clients')->where('id', $client->id)->value('metadata');
    expect($raw)->not->toContain('dcr-secret')->not->toContain('dcr-rat');
});

it('registers the client as Chatvia', function (): void {
    Http::fake([
        'auth.example.test/register' => Http::response(['client_id' => 'dcr-client'], 201),
    ]);

    app(McpOAuthRegistrar::class)->clientFor(morDcrMetadata());

    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://auth.example.test/register'
        && $request['client_name'] === 'Chatvia');
});

it('reuses the client already registered with the authorization server', function (): void {
    Http::fake();
    $existing = ConnectorOAuthClient::query()->create([
        'auth_server_url' => 'https://auth.example.test',
        'client_id' => 'registered-before',
        'token_endpoint' => 'https://auth.example.test/token',
        'authorization_endpoint' => 'https://auth.example.test/authorize',
    ]);

    $client = app(McpOAuthRegistrar::class)->clientFor(morDcrMetadata());

    expect($client->is($existing))->toBeTrue()
        ->and(ConnectorOAuthClient::query()->count())->toBe(1);
    Http::assertNothingSent();
});

it('stores nothing when the registration is rejected or returns no client id', function (mixed $body, int $status, string $message): void {
    Http::fake(['auth.example.test/register' => Http::response($body, $status)]);

    expect(fn () => app(McpOAuthRegistrar::class)->clientFor(morDcrMetadata()))
        ->toThrow(McpOAuthException::class, $message);

    expect(ConnectorOAuthClient::query()->count())->toBe(0);
})->with([
    'rejected' => ['invalid_redirect_uri', 400, 'Dynamic client registration was rejected (400)'],
    'no client id' => [['client_name' => 'Chatvia'], 201, 'Dynamic client registration returned no client_id.'],
]);

/**
 * A server without dynamic registration uses the OAuth app the admin set for
 * the connector (Settings → Connectors), read from config every time.
 */
function morGoogleMetadata(): OAuthServerMetadata
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

    $gmail = $registrar->clientFor(morGoogleMetadata(), $catalog->find('gmail'));
    $drive = $registrar->clientFor(morGoogleMetadata(), $catalog->find('google-drive'));

    expect($gmail->client_id)->toBe('gmail-app')
        ->and($drive->client_id)->toBe('drive-app')
        ->and($drive->client_secret)->toBe('drive-secret')
        ->and(ConnectorOAuthClient::query()->count())->toBe(0);
});

it('uses a rotated connector secret for the next connection', function (): void {
    config(['connectors.gmail.oauth_client_id' => 'gmail-app', 'connectors.gmail.oauth_client_secret' => 'old-secret']);
    $registrar = app(McpOAuthRegistrar::class);
    $registrar->clientFor(morGoogleMetadata(), app(ConnectorCatalog::class)->find('gmail'));

    config(['connectors.gmail.oauth_client_secret' => 'new-secret']);

    expect($registrar->clientFor(morGoogleMetadata(), app(ConnectorCatalog::class)->find('gmail'))->client_secret)->toBe('new-secret');
});

it('refuses a server without dynamic registration when the connector has no OAuth app', function (): void {
    config(['connectors.gmail.oauth_client_id' => null]);

    expect(fn () => app(McpOAuthRegistrar::class)->clientFor(morGoogleMetadata(), app(ConnectorCatalog::class)->find('gmail')))
        ->toThrow(McpOAuthException::class, 'requires a pre-registered OAuth client')
        ->and(fn () => app(McpOAuthRegistrar::class)->clientFor(morGoogleMetadata()))
        ->toThrow(McpOAuthException::class, 'requires a pre-registered OAuth client');
});
