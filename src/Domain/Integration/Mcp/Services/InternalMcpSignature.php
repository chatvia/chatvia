<?php

namespace Domain\Integration\Mcp\Services;

use Domain\Integration\Mcp\Models\AgentMcpServer;
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
 * The signature is only attached to first-party server rows
 * ({@see isFirstPartyServer()}) — never to third-party MCP servers, customer
 * webhooks, or a custom server a workspace pointed at one of our URLs.
 */
final class InternalMcpSignature
{
    /** Max clock skew / request age in seconds. */
    private const int MAX_AGE = 300;

    public const string TIMESTAMP_HEADER = 'X-Chatvia-Mcp-Timestamp';

    public const string SIGNATURE_HEADER = 'X-Chatvia-Mcp-Signature';

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
     * True only for a server row Chatvia itself defines: its `connector_slug`
     * is a first-party connector (built-in registry or a catalog preset served
     * from our own /mcp/ routes) and its URL is that connector's canonical
     * internal URL. A custom server whose URL merely points at our host has no
     * connector_slug and never qualifies.
     */
    public static function isFirstPartyServer(AgentMcpServer $server): bool
    {
        $slug = $server->connector_slug;
        if (! is_string($slug) || $slug === '') {
            return false;
        }

        $canonical = AgentMcpServer::canonicalConnectorUrl($slug);

        return $canonical !== null
            && self::isInternalUrl($canonical)
            && rtrim((string) $server->url, '/') === rtrim($canonical, '/');
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

        return [
            self::TIMESTAMP_HEADER => $timestamp,
            self::SIGNATURE_HEADER => self::sign($timestamp, $method, self::pathOf($url), $body, $identityHeaders),
        ];
    }

    /**
     * @param  array<string, string>  $identityHeaders  Every `X-Chatvia-*` header the request carries, except the timestamp and signature.
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

        return hash_equals($expected, $signature);
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
