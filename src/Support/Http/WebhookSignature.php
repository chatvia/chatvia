<?php

namespace Support\Http;

/**
 * Constant-time HMAC-SHA256 verification for server-to-server webhook/ingestion
 * callbacks (the chunker pipeline, document ingestion). The caller signs the
 * raw request body with a shared secret and sends the hex digest in a header.
 */
final class WebhookSignature
{
    public static function sign(string $payload, string $secret): string
    {
        return hash_hmac('sha256', $payload, $secret);
    }

    public static function verify(string $payload, ?string $signature, string $secret): bool
    {
        if (! is_string($signature) || $signature === '' || $secret === '') {
            return false;
        }

        return hash_equals(self::sign($payload, $secret), $signature);
    }
}
