<?php

use Domain\Channel\Teams\Services\TeamsActivityVerifier;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * The verifier is the webhook's whole authentication story, so it gets the
 * real treatment: an RSA key pair plays the Bot Framework, its public half is
 * served as a faked JWKS via OpenID discovery, and tokens are actually signed
 * and verified. No crypto is stubbed.
 */
beforeEach(function (): void {
    Cache::flush(); // the JWKS document is cached for a day

    $this->appId = 'f5e1f9d2-1111-2222-3333-444455556666';
    $this->serviceUrl = 'https://smba.trafficmanager.net/emea/';
    ['private' => $this->privatePem, 'jwk' => $this->publicJwk] = tavKeyPair();

    Http::fake([
        'https://login.botframework.com/v1/.well-known/openidconfiguration' => Http::response([
            'issuer' => 'https://api.botframework.com',
            'jwks_uri' => 'https://login.botframework.com/v1/.well-known/keys',
        ]),
        'https://login.botframework.com/v1/.well-known/keys' => Http::response([
            'keys' => [$this->publicJwk],
        ]),
    ]);
});

/**
 * The Bot Framework's key pair: the private key as PEM and the public half as
 * a JWK. Made once for the file, as making a 2048-bit key is slow.
 *
 * @return array{private: string, jwk: array<string, string>}
 */
function tavKeyPair(): array
{
    static $pair = null;

    if ($pair === null) {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privatePem);
        $details = openssl_pkey_get_details($key);
        $b64url = fn (string $bin): string => Str::rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');

        $pair = [
            'private' => $privatePem,
            'jwk' => ['kty' => 'RSA', 'use' => 'sig', 'kid' => 'test-kid', 'n' => $b64url($details['rsa']['n']), 'e' => $b64url($details['rsa']['e'])],
        ];
    }

    return $pair;
}

/**
 * @param  array<string, mixed>  $overrides
 */
function tavJwt(object $testCase, array $overrides = []): string
{
    $claims = array_merge([
        'iss' => 'https://api.botframework.com',
        'aud' => $testCase->appId,
        'exp' => time() + 600,
        'serviceurl' => $testCase->serviceUrl,
    ], $overrides);

    return JWT::encode($claims, $testCase->privatePem, 'RS256', 'test-kid');
}

it('accepts a token signed by the Bot Framework keys for this bot', function (): void {
    $jwt = tavJwt($this);

    expect((new TeamsActivityVerifier)->verify("Bearer {$jwt}", $this->appId))->not->toBeNull();
});

it('rejects a token addressed to a different bot', function (): void {
    $jwt = tavJwt($this, ['aud' => 'other-app-id']);

    expect((new TeamsActivityVerifier)->verify("Bearer {$jwt}", $this->appId))->toBeNull();
});

it('rejects a token from a different issuer', function (): void {
    $jwt = tavJwt($this, ['iss' => 'https://evil.example.com']);

    expect((new TeamsActivityVerifier)->verify("Bearer {$jwt}", $this->appId))->toBeNull();
});

it('rejects an expired token', function (): void {
    $jwt = tavJwt($this, ['exp' => time() - 1000]); // beyond the 300 s leeway

    expect((new TeamsActivityVerifier)->verify("Bearer {$jwt}", $this->appId))->toBeNull();
});

it('rejects a token whose serviceurl claim does not match the activity', function (): void {
    $jwt = tavJwt($this, ['serviceurl' => 'https://attacker.example.com/']);

    $verifier = new TeamsActivityVerifier;
    $claims = $verifier->verify("Bearer {$jwt}", $this->appId);

    expect($claims)->not->toBeNull()
        ->and($verifier->matchesServiceUrl($claims, $this->serviceUrl))->toBeFalse();
});

it('matches a service URL whether or not either side ends in a slash', function (string $claimed, string $serviceUrl): void {
    expect(new TeamsActivityVerifier()->matchesServiceUrl(['serviceurl' => $claimed], $serviceUrl))->toBeTrue();
})->with([
    'the claim without one' => ['https://smba.trafficmanager.net/emea', 'https://smba.trafficmanager.net/emea/'],
    'the activity without one' => ['https://smba.trafficmanager.net/emea/', 'https://smba.trafficmanager.net/emea'],
]);

it('refuses a token without a serviceurl claim, which every connector token carries', function (): void {
    $claims = ['iss' => 'https://api.botframework.com', 'aud' => $this->appId, 'exp' => time() + 600];
    $jwt = JWT::encode($claims, $this->privatePem, 'RS256', 'test-kid');

    $verifier = new TeamsActivityVerifier;
    $verified = $verifier->verify("Bearer {$jwt}", $this->appId);

    expect($verified)->not->toBeNull()
        ->and($verifier->matchesServiceUrl($verified, $this->serviceUrl))->toBeFalse();
});

it('refuses a plain-http service URL even when the claim matches it', function (): void {
    $serviceUrl = 'http://smba.trafficmanager.net/emea/';
    $verifier = new TeamsActivityVerifier;
    $verified = $verifier->verify('Bearer '.tavJwt($this, ['serviceurl' => $serviceUrl]), $this->appId);

    expect($verified)->not->toBeNull()
        ->and($verifier->matchesServiceUrl($verified, $serviceUrl))->toBeFalse();
});

it('fetches the keys again when a token is signed with a key the cache does not hold', function (): void {
    Cache::put('msteams:jwks', ['keys' => [[...$this->publicJwk, 'kid' => 'retired-kid']]], now()->addDay());

    $claims = (new TeamsActivityVerifier)->verify('Bearer '.tavJwt($this), $this->appId);

    expect($claims)->not->toBeNull();
    Http::assertSent(fn ($request): bool => $request->url() === 'https://login.botframework.com/v1/.well-known/keys');
});

it('fetches the keys again at most once in ten minutes, so made-up key ids cannot force a fetch per request', function (): void {
    Cache::put('msteams:jwks', ['keys' => [[...$this->publicJwk, 'kid' => 'retired-kid']]], now()->addDay());
    Cache::put('msteams:jwks:refreshed', true, now()->addMinutes(10));

    $claims = (new TeamsActivityVerifier)->verify('Bearer '.tavJwt($this), $this->appId);

    expect($claims)->toBeNull();
    Http::assertNothingSent();
});

it('rejects a forged token signed with a different key', function (): void {
    $foreignKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($foreignKey, $foreignPem);

    $jwt = JWT::encode([
        'iss' => 'https://api.botframework.com',
        'aud' => $this->appId,
        'exp' => time() + 600,
    ], $foreignPem, 'RS256', 'test-kid');

    expect((new TeamsActivityVerifier)->verify("Bearer {$jwt}", $this->appId))->toBeNull();
});

it('rejects a missing or malformed Authorization header', function (): void {
    $verifier = new TeamsActivityVerifier;

    expect($verifier->verify(null, $this->appId))->toBeNull()
        ->and($verifier->verify('', $this->appId))->toBeNull()
        ->and($verifier->verify('Bearer not-a-jwt', $this->appId))->toBeNull()
        ->and($verifier->verify('Basic dXNlcjpwYXNz', $this->appId))->toBeNull();
});

it('restores the global JWT leeway after verifying, so other JWT checks in the worker stay strict', function (): void {
    $original = JWT::$leeway;
    JWT::$leeway = 0;

    try {
        $verifier = new TeamsActivityVerifier;

        // Within the 300 s Teams skew window: accepted.
        expect($verifier->verify('Bearer '.tavJwt($this, ['exp' => time() - 60]), $this->appId))->not->toBeNull()
            ->and(JWT::$leeway)->toBe(0);

        // A failed decode must restore it too.
        $verifier->verify('Bearer not-a-jwt', $this->appId);
        expect(JWT::$leeway)->toBe(0);
    } finally {
        JWT::$leeway = $original;
    }
});
