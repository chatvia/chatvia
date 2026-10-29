<?php

use Domain\Channel\Teams\Services\TeamsActivityVerifier;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * The verifier is the webhook's whole authentication story, so it gets the
 * real treatment: an RSA key pair plays the Bot Framework, its public half is
 * served as a faked JWKS via OpenID discovery (cached for a day, so the cache
 * is flushed first), and tokens are actually signed and verified. No crypto
 * is stubbed. The verifier widens the process-wide JWT leeway only for its
 * own decode, and refetches the keys for an unknown key id at most once in
 * ten minutes.
 */
beforeEach(function (): void {
    Cache::flush();

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
        'exp' => now()->addMinutes(10)->timestamp,
        'serviceurl' => $testCase->serviceUrl,
    ], $overrides);

    return JWT::encode($claims, $testCase->privatePem, 'RS256', 'test-kid');
}

it('accepts only a Bot Framework token for this bot, within the skew window, leaving the global leeway as it was', function (): void {
    $foreignKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($foreignKey, $foreignPem);
    $forged = JWT::encode(['iss' => 'https://api.botframework.com', 'aud' => $this->appId, 'exp' => now()->addMinutes(10)->timestamp], $foreignPem, 'RS256', 'test-kid');
    $original = JWT::$leeway;
    JWT::$leeway = 0;

    try {
        $verifier = new TeamsActivityVerifier;

        expect($verifier->verify('Bearer '.tavJwt($this), $this->appId))->not->toBeNull()
            ->and($verifier->verify('Bearer '.tavJwt($this, ['exp' => now()->subMinute()->timestamp]), $this->appId))->not->toBeNull()
            ->and(JWT::$leeway)->toBe(0)
            ->and($verifier->verify('Bearer '.tavJwt($this, ['aud' => 'other-app-id']), $this->appId))->toBeNull()
            ->and($verifier->verify('Bearer '.tavJwt($this, ['iss' => 'https://evil.example.com']), $this->appId))->toBeNull()
            ->and($verifier->verify('Bearer '.tavJwt($this, ['exp' => now()->subSeconds(1000)->timestamp]), $this->appId))->toBeNull()
            ->and($verifier->verify("Bearer {$forged}", $this->appId))->toBeNull()
            ->and($verifier->verify(null, $this->appId))->toBeNull()
            ->and($verifier->verify('', $this->appId))->toBeNull()
            ->and($verifier->verify('Bearer not-a-jwt', $this->appId))->toBeNull()
            ->and($verifier->verify('Basic dXNlcjpwYXNz', $this->appId))->toBeNull()
            ->and(JWT::$leeway)->toBe(0);
    } finally {
        JWT::$leeway = $original;
    }
});

it('matches only an https service URL its verified token names, slash or not', function (): void {
    $verifier = new TeamsActivityVerifier;
    $withoutClaim = JWT::encode(['iss' => 'https://api.botframework.com', 'aud' => $this->appId, 'exp' => now()->addMinutes(10)->timestamp], $this->privatePem, 'RS256', 'test-kid');
    $plainHttp = 'http://smba.trafficmanager.net/emea/';

    $replayed = $verifier->verify('Bearer '.tavJwt($this, ['serviceurl' => 'https://attacker.example.com/']), $this->appId);
    $unclaimed = $verifier->verify("Bearer {$withoutClaim}", $this->appId);
    $http = $verifier->verify('Bearer '.tavJwt($this, ['serviceurl' => $plainHttp]), $this->appId);

    expect($replayed)->not->toBeNull()
        ->and($verifier->matchesServiceUrl($replayed, $this->serviceUrl))->toBeFalse()
        ->and($unclaimed)->not->toBeNull()
        ->and($verifier->matchesServiceUrl($unclaimed, $this->serviceUrl))->toBeFalse()
        ->and($http)->not->toBeNull()
        ->and($verifier->matchesServiceUrl($http, $plainHttp))->toBeFalse()
        ->and($verifier->matchesServiceUrl(['serviceurl' => 'https://smba.trafficmanager.net/emea'], 'https://smba.trafficmanager.net/emea/'))->toBeTrue()
        ->and($verifier->matchesServiceUrl(['serviceurl' => 'https://smba.trafficmanager.net/emea/'], 'https://smba.trafficmanager.net/emea'))->toBeTrue();
});

it('fetches the keys again for a key the cache does not hold, at most once in ten minutes', function (): void {
    $retired = ['keys' => [[...$this->publicJwk, 'kid' => 'retired-kid']]];
    $verifier = new TeamsActivityVerifier;

    Cache::put('msteams:jwks', $retired, now()->addDay());
    $rotated = $verifier->verify('Bearer '.tavJwt($this), $this->appId);
    Cache::put('msteams:jwks', $retired, now()->addDay());
    $throttled = $verifier->verify('Bearer '.tavJwt($this), $this->appId);

    expect($rotated)->not->toBeNull()
        ->and($throttled)->toBeNull();
    Http::assertSentCount(2);
    Http::assertSent(fn ($request): bool => $request->url() === 'https://login.botframework.com/v1/.well-known/keys');
});
