<?php

declare(strict_types=1);

namespace Support\License;

use InvalidArgumentException;

/**
 * Checks license keys with Ed25519, offline. The chatvia.ai site sells and
 * signs keys with a secret key that never ships; every install checks a key
 * against the public key in `config/chatvia.php`.
 *
 * A key reads `cvl1.<terms>.<signature>`, both parts base64url. The signature
 * covers the version and the terms, so neither can be swapped. The site signs
 * the same way {@see sign()} does; a shared test key keeps the two in step.
 */
final class LicenseSignature
{
    private const string VERSION = 'cvl1';

    /**
     * The key for these terms.
     *
     * @param  string  $secretKey  A base64 Ed25519 secret key.
     */
    public static function sign(LicenseKey $license, string $secretKey): string
    {
        $secret = self::rawKey($secretKey, SODIUM_CRYPTO_SIGN_SECRETKEYBYTES)
            ?? throw new InvalidArgumentException('The secret key is not a base64 Ed25519 secret key.');

        $terms = self::VERSION.'.'.self::encode(json_encode($license->toPayload(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $terms.'.'.self::encode(sodium_crypto_sign_detached($terms, $secret));
    }

    /**
     * The terms of a key signed by the secret half of $publicKey, or null for
     * anything else. White space is ignored, since a pasted key may wrap.
     *
     * @param  string|null  $publicKey  A base64 Ed25519 public key.
     */
    public static function verify(string $key, ?string $publicKey): ?LicenseKey
    {
        $public = self::rawKey((string) $publicKey, SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES);
        $parts = explode('.', (string) preg_replace('/\s+/', '', $key));

        if ($public === null || count($parts) !== 3 || $parts[0] !== self::VERSION) {
            return null;
        }

        $signature = self::decode($parts[2]);

        if ($signature === null || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES
            || ! sodium_crypto_sign_verify_detached($signature, $parts[0].'.'.$parts[1], $public)) {
            return null;
        }

        $payload = json_decode((string) self::decode($parts[1]), true);

        return is_array($payload) ? LicenseKey::fromPayload($payload) : null;
    }

    /** The raw bytes of a base64 key, or null when it is not one of $length bytes. */
    private static function rawKey(string $key, int $length): ?string
    {
        $raw = base64_decode(trim($key), true);

        return is_string($raw) && strlen($raw) === $length ? $raw : null;
    }

    private static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private static function decode(string $text): ?string
    {
        $bytes = base64_decode(strtr($text, '-_', '+/'), true);

        return $bytes === false ? null : $bytes;
    }
}
