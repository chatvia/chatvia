<?php

namespace Domain\Integration\Mcp\Services;

use Illuminate\Support\Str;

/**
 * Proof-of-origin for calls to Chatvia's OWN built-in MCP servers
 * (/mcp/weather, /mcp/currency, /mcp/charts, /mcp/leads, /mcp/web-search).
 *
 * These run as public HTTP routes but must only be reachable by Chatvia's own
 * server-side tool loop (McpClient). We attach a short-lived HMAC over a
 * timestamp; VerifyInternalMcpRequest middleware rejects anything without a
 * valid, fresh signature, closing the "anyone on the internet can POST a
 * tools/call" hole. The signature is NEVER sent to third-party MCP servers or
 * customer webhooks — only to URLs on our own host.
 */
final class InternalMcpSignature
{
    /** Max clock skew / request age in seconds. */
    private const MAX_AGE = 300;

    public static function secret(): string
    {
        $secret = (string) config('services.builtin_mcp.secret');

        return $secret !== '' ? $secret : (string) config('app.key');
    }

    public static function isInternalUrl(string $url): bool
    {
        $base = rtrim((string) config('app.url'), '/').'/mcp/';

        return Str::startsWith($url, $base);
    }

    /**
     * @return array<string, string>
     */
    public static function headers(): array
    {
        $timestamp = (string) now()->timestamp;

        return [
            self::TIMESTAMP_HEADER => $timestamp,
            self::SIGNATURE_HEADER => hash_hmac('sha256', $timestamp, self::secret()),
        ];
    }

    public const TIMESTAMP_HEADER = 'X-Chatvia-Mcp-Timestamp';

    public const SIGNATURE_HEADER = 'X-Chatvia-Mcp-Signature';

    public static function verify(?string $timestamp, ?string $signature): bool
    {
        $timestamp = (string) $timestamp;
        $signature = (string) $signature;

        if ($timestamp === '' || $signature === '' || ! ctype_digit($timestamp)) {
            return false;
        }

        if (abs(now()->timestamp - (int) $timestamp) > self::MAX_AGE) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp, self::secret());

        return hash_equals($expected, $signature);
    }
}
