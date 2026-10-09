<?php

namespace Domain\Integration\Mcp\Services;

use Domain\Integration\Mcp\Exceptions\McpException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Proof-of-origin for calls to Chatvia's OWN built-in MCP servers
 * (/mcp/weather, /mcp/currency, /mcp/charts, /mcp/forms, /mcp/perplexity,
 * /mcp/account-analytics).
 *
 * These run as public HTTP routes but must only be reachable by Chatvia's own
 * server-side tool loop (McpClient). The HMAC covers the timestamp, HTTP
 * method, path, a SHA-256 of the body and every `X-Chatvia-*` identity header
 * (agent, user, workspace, conversation, chatter). So a captured request can't
 * be replayed with a different body or a different agent/workspace header, and
 * the receiving tools can trust those headers. VerifyInternalMcpRequest
 * rejects anything without a valid, fresh signature.
 *
 * Every request also carries a random one-time nonce. It travels as one more
 * signed `X-Chatvia-*` header, so the canonical string keeps its shape, and the
 * verifier remembers each nonce (in the cache) until the signature expires: an
 * identical copy of a captured request is refused too. A request without a
 * nonce is accepted only while `services.builtin_mcp.allow_missing_nonce` is
 * on, which covers a deploy where workers still run the previous code.
 *
 * The signature is only attached to first-party server rows (see
 * McpClient, which asks the connector catalog whether a row is one) — never to
 * third-party MCP servers, customer webhooks, or a custom server a workspace
 * pointed at one of our URLs.
 *
 * The `X-Chatvia-*` header names are part of the public tool-call contract,
 * so they keep this name even when the product is rebranded.
 */
final class InternalMcpSignature
{
    /** Max clock skew / request age in seconds. */
    private const int MAX_AGE = 300;

    public const string TIMESTAMP_HEADER = 'X-Chatvia-Mcp-Timestamp';

    public const string SIGNATURE_HEADER = 'X-Chatvia-Mcp-Signature';

    /** One-time value; signed like the identity headers, refused when seen twice. */
    public const string NONCE_HEADER = 'X-Chatvia-Mcp-Nonce';

    private const string NONCE_CACHE_PREFIX = 'internal-mcp-nonce:';

    /** Prefix of the caller-identity headers the signature covers. */
    public const string IDENTITY_HEADER_PREFIX = 'x-chatvia-';

    public static function secret(): string
    {
        $secret = config('services.builtin_mcp.secret');

        if (blank($secret)) {
            throw new McpException('Missing secret');
        }

        return $secret;
    }

    public static function isInternalUrl(string $url): bool
    {
        $base = rtrim((string) config('app.url'), '/').'/mcp/';

        return Str::startsWith($url, $base);
    }

    /**
     * Signature headers for one request.
     *
     * @param  array<string, string>  $identityHeaders  Every `X-Chatvia-*` header the request carries.
     * @return array<string, string>
     */
    public static function headers(string $method, string $url, string $body, array $identityHeaders = []): array
    {
        $timestamp = (string) now()->timestamp;
        $nonce = Str::random(40);
        $identityHeaders[self::NONCE_HEADER] = $nonce;

        return [
            self::TIMESTAMP_HEADER => $timestamp,
            self::NONCE_HEADER => $nonce,
            self::SIGNATURE_HEADER => self::sign($timestamp, $method, self::pathOf($url), $body, $identityHeaders),
        ];
    }

    /**
     * True for a request signed with our secret, inside the time window, whose
     * nonce has not been seen before. A valid request uses up its nonce.
     *
     * @param  array<string, string>  $identityHeaders  Every `X-Chatvia-*` header the request carries (the nonce included), except the timestamp and signature.
     */
    public static function verify(
        ?string $timestamp,
        ?string $signature,
        string $method,
        string $path,
        string $body,
        array $identityHeaders,
    ): bool {
        $timestamp = (string) $timestamp;
        $signature = (string) $signature;

        if ($timestamp === '' || $signature === '' || ! ctype_digit($timestamp)) {
            return false;
        }

        if (abs(now()->timestamp - (int) $timestamp) > self::MAX_AGE) {
            return false;
        }

        $expected = self::sign($timestamp, $method, $path, $body, $identityHeaders);

        if (! hash_equals($expected, $signature)) {
            return false;
        }

        $nonce = self::identityHeadersFrom($identityHeaders)[strtolower(self::NONCE_HEADER)] ?? null;

        return self::claimNonce($nonce, (int) $timestamp);
    }

    /**
     * Remembers the nonce until the signature it belongs to expires, and says
     * whether this is its first use. The cache write is atomic, so two copies
     * of one request racing each other cannot both pass.
     */
    private static function claimNonce(?string $nonce, int $timestamp): bool
    {
        if ($nonce === null || $nonce === '') {
            return (bool) config('services.builtin_mcp.allow_missing_nonce', false);
        }

        if (preg_match('/^[A-Za-z0-9]{16,128}$/', $nonce) !== 1) {
            return false;
        }

        $secondsUntilExpiry = $timestamp + self::MAX_AGE - now()->timestamp + 1;

        return Cache::add(self::NONCE_CACHE_PREFIX.$nonce, true, max(1, $secondsUntilExpiry));
    }

    /**
     * Pick the identity headers out of a full header map (any letter case;
     * values may be strings or lists, as Symfony's HeaderBag returns them).
     * The timestamp and signature headers are excluded.
     *
     * @param  array<string, mixed>  $headers
     * @return array<string, string>
     */
    public static function identityHeadersFrom(array $headers): array
    {
        $excluded = [strtolower(self::TIMESTAMP_HEADER), strtolower(self::SIGNATURE_HEADER)];
        $identity = [];

        foreach ($headers as $name => $value) {
            $name = strtolower((string) $name);

            if (! str_starts_with($name, self::IDENTITY_HEADER_PREFIX) || in_array($name, $excluded, true)) {
                continue;
            }

            $identity[$name] = is_array($value) ? (string) ($value[0] ?? '') : (string) $value;
        }

        return $identity;
    }

    /**
     * @param  array<string, string>  $identityHeaders
     */
    private static function sign(string $timestamp, string $method, string $path, string $body, array $identityHeaders): string
    {
        $identity = self::identityHeadersFrom($identityHeaders);
        ksort($identity);

        $lines = [];
        foreach ($identity as $name => $value) {
            $lines[] = $name.':'.trim($value);
        }

        $canonical = implode("\n", [
            $timestamp,
            strtoupper($method),
            '/'.trim($path, '/'),
            hash('sha256', $body),
            implode("\n", $lines),
        ]);

        return hash_hmac('sha256', $canonical, self::secret());
    }

    private static function pathOf(string $url): string
    {
        return (string) (parse_url($url, PHP_URL_PATH) ?? '');
    }
}
