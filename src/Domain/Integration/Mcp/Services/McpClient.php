<?php

namespace Domain\Integration\Mcp\Services;

use Closure;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Mcp\Contracts\AccessTokenRefresher;
use Domain\Integration\Mcp\Data\McpToolDefinitionData;
use Domain\Integration\Mcp\Data\McpToolResultData;
use Domain\Integration\Mcp\Enums\McpAuthType;
use Domain\Integration\Mcp\Exceptions\McpException;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Support\Http\SafeUrl;
use Throwable;

/**
 * Thin JSON-RPC 2.0 client for MCP streamable-HTTP servers.
 *
 * Each public method opens a fresh session (initialize → notifications/
 * initialized → the method → close): simple and safe. Only single-response
 * JSON-RPC is consumed — `tools/list` and `tools/call`, the two methods the
 * chat flow needs; an event-stream reply is read as one SSE frame.
 */
class McpClient
{
    private const string PROTOCOL_VERSION = '2025-06-18';

    private const string CLIENT_VERSION = '1.0';

    /** How much of a failed response body an error message keeps. */
    private const int ERROR_BODY_LENGTH = 200;

    public function __construct(
        private readonly AccessTokenRefresher $tokens,
        private readonly ConnectorCatalog $catalog,
    ) {}

    /**
     * Probe a server with `initialize` + `tools/list` and return its tool
     * catalog. Used at connect time and on refresh; throws McpException on
     * any protocol failure.
     *
     * @return list<McpToolDefinitionData>
     */
    public function fetchTools(AgentMcpServer $server): array
    {
        return $this->inSession($server, [], function (?string $sessionId) use ($server): array {
            $tools = $this->rpc($server, $sessionId, 'tools/list', (object) [])['body']['result']['tools'] ?? null;

            if (! is_array($tools)) {
                throw new McpException('MCP server returned a malformed tools/list response.');
            }

            return McpToolDefinitionData::listFrom($tools);
        });
    }

    /**
     * Call a tool. The server's result envelope (`content`, `isError`,
     * `structuredContent`) is read once, here, into what the model reads and
     * what the chat UI renders ({@see McpToolResultData}). A tool that
     * reports a failure in the envelope (`isError`) is a result, not an
     * exception; a JSON-RPC error or a broken reply throws McpException.
     *
     * @param  array<string, mixed>  $arguments
     * @param  array<string, string>  $identityHeaders  Caller-identity headers (agent + user) stamped on every request in the session.
     */
    public function callTool(AgentMcpServer $server, string $name, array $arguments, array $identityHeaders = []): McpToolResultData
    {
        return $this->inSession($server, $identityHeaders, function (?string $sessionId) use ($server, $name, $arguments, $identityHeaders): McpToolResultData {
            $body = $this->rpc($server, $sessionId, 'tools/call', [
                'name' => $name,
                // An object even with no arguments: an empty PHP array encodes
                // as `[]`, which strict servers (Stripe) refuse.
                'arguments' => (object) $arguments,
            ], $identityHeaders)['body'];

            // Only our own servers' results may carry blocks the chat renders.
            $result = $body['result'] ?? null;
            if (is_array($result)) {
                return $this->isFirstPartyServer($server)
                    ? McpToolResultData::fromFirstPartyEnvelope($result)
                    : McpToolResultData::fromEnvelope($result);
            }

            $error = $body['error'] ?? null;
            if (is_array($error) && isset($error['message'])) {
                throw McpException::reportedByServer((string) $error['message'], (string) $error['message']);
            }

            throw new McpException('tool/call did not return a result');
        });
    }

    /**
     * Run one method inside a fresh MCP session: refresh the OAuth token if
     * needed, do the `initialize` handshake, run `$method` with the session id
     * the server issued (if any), then release the session.
     *
     * @template TResult
     *
     * @param  array<string, string>  $identityHeaders
     * @param  Closure(?string): TResult  $method
     * @return TResult
     */
    private function inSession(AgentMcpServer $server, array $identityHeaders, Closure $method): mixed
    {
        $this->ensureFreshToken($server);

        $sessionId = $this->rpc($server, null, 'initialize', [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities' => (object) [],
            'clientInfo' => $this->clientInfo(),
        ], $identityHeaders)['_session_id'];

        $this->notify($server, $sessionId, 'notifications/initialized', identityHeaders: $identityHeaders);

        $result = $method($sessionId);

        $this->closeSession($server, $sessionId, $identityHeaders);

        return $result;
    }

    /**
     * For OAuth-backed connectors, refresh the access token if it is about
     * to expire so the upcoming request goes out with a valid bearer. A no-op
     * for token-paste connectors.
     */
    private function ensureFreshToken(AgentMcpServer $server): void
    {
        if (! $server->accessTokenIsStale()) {
            return;
        }

        try {
            $this->tokens->refreshIfStale($server);
        } catch (Throwable $e) {
            // Don't fail here — let the server answer 401, so the row's
            // last_error shows the server's real message instead of our guess.
            Log::warning('MCP token refresh failed', [
                'server_id' => $server->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send one HTTP request to the server. The JSON body is encoded here (not
     * by the HTTP client) so the exact bytes can be signed for our own
     * built-in servers. A null payload sends an empty-bodied DELETE.
     *
     * @param  array<string, mixed>|null  $payload
     * @param  array<string, string>  $identityHeaders
     */
    private function send(AgentMcpServer $server, ?string $sessionId, ?array $payload, array $identityHeaders = []): Response
    {
        $httpMethod = $payload === null ? 'DELETE' : 'POST';
        $body = $payload === null ? '' : json_encode($payload, JSON_THROW_ON_ERROR);
        $firstParty = $this->isFirstPartyServer($server);
        $url = $firstParty ? InternalMcpSignature::transportUrl($server->url) : $server->url;

        $request = $this->request($server, $firstParty, $url, $sessionId, $identityHeaders, $httpMethod, $body);

        return $payload === null
            ? $request->delete($url)
            : $request->withBody($body, 'application/json')->post($url);
    }

    /**
     * Build a fresh PendingRequest for a single MCP call. Always builds anew
     * so headers can't accumulate across calls — Laravel's withHeaders uses
     * array_merge_recursive, which would nest repeated keys into arrays
     * (sending the same header twice on the wire). Notion in particular
     * treats two `Mcp-Session-Id` headers as a comma-joined value and
     * 404s the request as "Session not found".
     *
     * @param  array<string, string>  $identityHeaders
     */
    private function request(AgentMcpServer $server, bool $firstParty, string $url, ?string $sessionId, array $identityHeaders, string $httpMethod, string $body): PendingRequest
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json, text/event-stream',
            'MCP-Protocol-Version' => self::PROTOCOL_VERSION,
            ...$this->customHeaders($server, $firstParty),
            ...$this->authHeaders($server),
        ];

        if ($sessionId) {
            $headers['Mcp-Session-Id'] = $sessionId;
        }

        // SSRF guard: a custom MCP server URL is user-supplied. Re-validate at
        // dispatch time (DNS can change since save), connect to the address
        // that was checked, and disable redirects so a public host can't 30x
        // into internal infrastructure (unless Settings → Security allows
        // private addresses). One of our own servers is the exception: its
        // address is APP_URL or the internal URL, the operator's config rather
        // than a workspace's input, and may well be private (a container
        // network, a local or intranet install).
        $clientOptions = $firstParty
            ? SafeUrl::clientOptions()
            : SafeUrl::pinnedClientOptions($server->url);

        // Identity headers are stamped last so a server's custom headers
        // can't shadow them.
        $headers = array_merge($headers, $identityHeaders);

        // Proof-of-origin for OUR OWN built-in MCP endpoints so they reject
        // anonymous internet callers. It signs the method, path, body and the
        // identity headers. Never sent to third-party servers.
        if ($firstParty) {
            $headers = array_merge($headers, InternalMcpSignature::headers(
                $httpMethod,
                $server->url,
                $body,
                InternalMcpSignature::identityHeadersFrom($headers),
            ));
        }

        return Http::timeout(max(1, (int) ceil($server->timeout_ms / 1000)))
            ->withOptions($clientOptions)
            ->withHeaders($headers);
    }

    /**
     * Headers the workspace configured on the server. Our own servers never
     * take custom headers, so nothing user-defined can slip into the signed
     * identity headers.
     *
     * @return array<string, string>
     */
    private function customHeaders(AgentMcpServer $server, bool $firstParty): array
    {
        if ($firstParty) {
            return [];
        }

        $headers = [];
        foreach ((array) ($server->headers ?? []) as $header) {
            if (isset($header['key'], $header['value'])) {
                $headers[$header['key']] = $header['value'];
            }
        }

        return $headers;
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(AgentMcpServer $server): array
    {
        if (! $server->auth_value) {
            return [];
        }

        return match ($server->auth_type) {
            McpAuthType::Bearer => ['Authorization' => 'Bearer '.$server->auth_value],
            McpAuthType::Header => ['X-Auth' => $server->auth_value],
            McpAuthType::None => [],
        };
    }

    /**
     * True only for a server row Chatvia itself defines: its connector is a
     * first-party one served from our own /mcp/ routes and its URL is that
     * connector's canonical internal URL. A custom server whose URL merely
     * points at our host has no connector slug and never qualifies.
     */
    private function isFirstPartyServer(AgentMcpServer $server): bool
    {
        return $this->catalog->sharesCentralCatalog($server->connector_slug, $server->url)
            && InternalMcpSignature::isInternalUrl($server->url);
    }

    /**
     * Send a JSON-RPC request and return the parsed envelope plus the session
     * id the server issued in its response header (streamable-HTTP servers
     * may issue one we must echo back).
     *
     * @param  array<string, mixed>|object  $params
     * @param  array<string, string>  $identityHeaders
     * @return array{body: array<string, mixed>, _session_id: ?string}
     */
    private function rpc(
        AgentMcpServer $server,
        ?string $sessionId,
        string $method,
        array|object $params,
        array $identityHeaders = [],
    ): array {
        $id = (string) Str::uuid();

        try {
            $response = $this->send($server, $sessionId, [
                'jsonrpc' => '2.0',
                'id' => $id,
                'method' => $method,
                'params' => $params,
            ], $identityHeaders);
        } catch (Throwable $e) {
            throw new McpException('Could not reach MCP server: '.$e->getMessage(), $e->getCode(), previous: $e);
        }

        if ($response->failed()) {
            throw new McpException(sprintf(
                'MCP server returned HTTP %d: %s',
                $response->status(),
                Str::substr($response->body(), 0, self::ERROR_BODY_LENGTH),
            ), $response->status());
        }

        $body = $this->decodeResponseBody($response);
        if (! is_array($body)) {
            throw new McpException('MCP server returned non-JSON for '.$method);
        }

        if (($body['id'] ?? null) !== $id) {
            throw new McpException(
                sprintf('MCP response id mismatch for %s (sent %s, got %s)', $method, $id, $body['id'] ?? 'null')
            );
        }

        if (isset($body['error']['message'])) {
            // Surface the protocol-level error as an exception; callers may
            // catch and convert it to a user-facing string.
            throw McpException::reportedByServer(
                'MCP error from '.$method.': '.$body['error']['message'],
                (string) $body['error']['message'],
            );
        }

        return [
            'body' => $body,
            '_session_id' => $response->header('Mcp-Session-Id') ?: null,
        ];
    }

    /**
     * Fire-and-forget JSON-RPC notification (no id, no expected response body).
     *
     * @param  array<string, mixed>|object  $params
     * @param  array<string, string>  $identityHeaders
     */
    private function notify(AgentMcpServer $server, ?string $sessionId, string $method, array|object $params = [], array $identityHeaders = []): void
    {
        try {
            $this->send($server, $sessionId, [
                'jsonrpc' => '2.0',
                'method' => $method,
                'params' => $params,
            ], $identityHeaders);
        } catch (Throwable) {
            // Notifications are best-effort.
        }
    }

    /**
     * Streamable-HTTP servers expect a DELETE on the same endpoint, with the
     * session header, to release server-side state. Best-effort.
     *
     * @param  array<string, string>  $identityHeaders
     */
    private function closeSession(AgentMcpServer $server, ?string $sessionId, array $identityHeaders = []): void
    {
        if (! $sessionId) {
            return;
        }

        try {
            $this->send($server, $sessionId, null, $identityHeaders);
        } catch (Throwable) {
            // Best-effort.
        }
    }

    /**
     * MCP streamable-HTTP responses come back as one of:
     *   - `application/json` — plain JSON, the simple case.
     *   - `text/event-stream` — a single-event SSE frame whose `data:` line
     *     carries the JSON-RPC response (Linear, Atlassian, …).
     *
     * Returns the decoded JSON-RPC envelope or null if neither shape parses.
     *
     * @return array<string, mixed>|null
     */
    private function decodeResponseBody(Response $response): ?array
    {
        $contentType = Str::lower($response->header('Content-Type'));

        if (! Str::contains($contentType, 'text/event-stream')) {
            $decoded = $response->json();

            return is_array($decoded) ? $decoded : null;
        }

        $dataLines = Str::of($response->body())
            ->split('/\r?\n/')
            ->filter(fn (string $line): bool => Str::startsWith($line, 'data:'))
            ->map(fn (string $line): string => Str::ltrim(Str::chopStart($line, 'data:')));

        if ($dataLines->isEmpty()) {
            return null;
        }

        $decoded = json_decode($dataLines->implode(''), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * How this app introduces itself to MCP servers (the `initialize`
     * handshake): the configured brand name as a slug.
     *
     * @return array{name: string, version: string}
     */
    private function clientInfo(): array
    {
        return [
            'name' => 'chatvia',
            'version' => self::CLIENT_VERSION,
        ];
    }
}
