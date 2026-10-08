<?php

use Domain\Channel\Teams\Services\TeamsActivityVerifier;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The verifier is the webhook's whole authentication story, so it gets the
 * real treatment: a throwaway RSA key pair plays the Bot Framework, its
 * public half is served as a faked JWKS via OpenID discovery, and tokens are
 * actually signed and verified. No crypto is stubbed.
 */
const TEAMS_VERIFIER_APP_ID = 'f5e1f9d2-1111-2222-3333-444455556666';
const TEAMS_VERIFIER_SERVICE_URL = 'https://smba.trafficmanager.net/emea/';

beforeEach(function (): void {
    Cache::flush(); // the JWKS document is cached for a day

    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $privatePem);
    $details = openssl_pkey_get_details($key);

    $this->privatePem = $privatePem;

    $b64url = fn (string $bin): string => rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');

    Http::fake([
        'https://login.botframework.com/v1/.well-known/openidconfiguration' => Http::response([
            'issuer' => 'https://api.botframework.com',
            'jwks_uri' => 'https://login.botframework.com/v1/.well-known/keys',
        ]),
        'https://login.botframework.com/v1/.well-known/keys' => Http::response([
            'keys' => [
                [
                    'kty' => 'RSA',
                    'use' => 'sig',
                    'kid' => 'test-kid',
                    'n' => $b64url($details['rsa']['n']),
                    'e' => $b64url($details['rsa']['e']),
                ],
            ],
        ]),
    ]);
});

/**
 * @param  array<string, mixed>  $overrides
 */
function teamsConnectorJwt(object $testCase, array $overrides = []): string
{
    $claims = array_merge([
        'iss' => 'https://api.botframework.com',
        'aud' => TEAMS_VERIFIER_APP_ID,
        'exp' => time() + 600,
        'serviceurl' => TEAMS_VERIFIER_SERVICE_URL,
    ], $overrides);

    return JWT::encode($claims, $testCase->privatePem, 'RS256', 'test-kid');
}

it('accepts a token signed by the Bot Framework keys for this bot', function (): void {
    $jwt = teamsConnectorJwt($this);

    expect((new TeamsActivityVerifier)->verify("Bearer {$jwt}", TEAMS_VERIFIER_APP_ID))->not->toBeNull();
});

it('rejects a token addressed to a different bot', function (): void {
    $jwt = teamsConnectorJwt($this, ['aud' => 'other-app-id']);

    expect((new TeamsActivityVerifier)->verify("Bearer {$jwt}", TEAMS_VERIFIER_APP_ID))->toBeNull();
});

it('rejects a token from a different issuer', function (): void {
    $jwt = teamsConnectorJwt($this, ['iss' => 'https://evil.example.com']);

    expect((new TeamsActivityVerifier)->verify("Bearer {$jwt}", TEAMS_VERIFIER_APP_ID))->toBeNull();
});

it('rejects an expired token', function (): void {
    $jwt = teamsConnectorJwt($this, ['exp' => time() - 1000]); // beyond the 300 s leeway

    expect((new TeamsActivityVerifier)->verify("Bearer {$jwt}", TEAMS_VERIFIER_APP_ID))->toBeNull();
});

it('rejects a token whose serviceurl claim does not match the activity', function (): void {
    $jwt = teamsConnectorJwt($this, ['serviceurl' => 'https://attacker.example.com/']);

    $verifier = new TeamsActivityVerifier;
    $claims = $verifier->verify("Bearer {$jwt}", TEAMS_VERIFIER_APP_ID);

    expect($claims)->not->toBeNull()
        ->and($verifier->matchesServiceUrl($claims, TEAMS_VERIFIER_SERVICE_URL))->toBeFalse();
});

it('accepts a token without a serviceurl claim (claim is optional)', function (): void {
    $claims = ['iss' => 'https://api.botframework.com', 'aud' => TEAMS_VERIFIER_APP_ID, 'exp' => time() + 600];
    $jwt = JWT::encode($claims, $this->privatePem, 'RS256', 'test-kid');

    $verifier = new TeamsActivityVerifier;
    $verified = $verifier->verify("Bearer {$jwt}", TEAMS_VERIFIER_APP_ID);

    expect($verified)->not->toBeNull()
        ->and($verifier->matchesServiceUrl($verified, TEAMS_VERIFIER_SERVICE_URL))->toBeTrue();
});

it('rejects a forged token signed with a different key', function (): void {
    $foreignKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($foreignKey, $foreignPem);

    $jwt = JWT::encode([
        'iss' => 'https://api.botframework.com',
        'aud' => TEAMS_VERIFIER_APP_ID,
        'exp' => time() + 600,
    ], $foreignPem, 'RS256', 'test-kid');

    expect((new TeamsActivityVerifier)->verify("Bearer {$jwt}", TEAMS_VERIFIER_APP_ID))->toBeNull();
});

it('rejects a missing or malformed Authorization header', function (): void {
    $verifier = new TeamsActivityVerifier;

    expect($verifier->verify(null, TEAMS_VERIFIER_APP_ID))->toBeNull()
        ->and($verifier->verify('', TEAMS_VERIFIER_APP_ID))->toBeNull()
        ->and($verifier->verify('Bearer not-a-jwt', TEAMS_VERIFIER_APP_ID))->toBeNull()
        ->and($verifier->verify('Basic dXNlcjpwYXNz', TEAMS_VERIFIER_APP_ID))->toBeNull();
});

it('restores the global JWT leeway after verifying, so other JWT checks in the worker stay strict', function (): void {
    $original = JWT::$leeway;
    JWT::$leeway = 0;

    try {
        $verifier = new TeamsActivityVerifier;

        // Within the 300 s Teams skew window: accepted.
        expect($verifier->verify('Bearer '.teamsConnectorJwt($this, ['exp' => time() - 60]), TEAMS_VERIFIER_APP_ID))->not->toBeNull()
            ->and(JWT::$leeway)->toBe(0);

        // A failed decode must restore it too.
        $verifier->verify('Bearer not-a-jwt', TEAMS_VERIFIER_APP_ID);
        expect(JWT::$leeway)->toBe(0);
    } finally {
        JWT::$leeway = $original;
    }
});
