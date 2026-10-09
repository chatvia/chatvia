<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Services\OAuth;

/**
 * PKCE (RFC 7636) with the S256 method, which OAuth 2.1 makes mandatory: a
 * random verifier stays on our side, and only its SHA-256 challenge travels
 * in the authorize URL, so a stolen authorization code is useless without it.
 */
final class Pkce
{
    /**
     * 32 random bytes → a 43-character base64url string, the minimum length
     * RFC 7636 requires and well under its 128-character ceiling.
     */
    public static function verifier(): string
    {
        return self::base64Url(random_bytes(32));
    }

    public static function challenge(string $verifier): string
    {
        return self::base64Url(hash('sha256', $verifier, true));
    }

    private static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
