<?php

namespace Domain\Chat\Services\Tooling\Mcp;

use Domain\Agents\Models\AgentMcpServer;
use Domain\Connectors\Services\OAuth\McpOAuthFlow;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Thin JSON-RPC 2.0 client for MCP HTTP / streamable-HTTP servers.
 *
 * Each public method opens a fresh session (initialize → notifications/
 * initialized → user method → close) — simple and safe. Session caching is
 * an optimization we can add later if profiling demands it.
 *
 * Streams (text/event-stream upgrades) are not handled in v1; we only
 * consume single-response JSON-RPC for `tools/list` and `tools/call`, which
 * are the two methods the chat flow needs.
 */
class McpClient
{
    private const PROTOCOL_VERSION = '2025-06-18';

    private const CLIENT_INFO = [
        'name' => 'chatvia',
        'version' => '1.0',
    ];

    /**
     * Probe a server with `initialize` + `tools/list`. Returns the tool
     * catalog as the server emits it. Used at connect-time and on manual
     * refresh; throws McpException on any protocol failure.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchTools(AgentMcpServer $server): array
    {
        $this->ensureFreshToken($server);

        $sessionId = null;

        $initResponse = $this->rpc($server, $sessionId, 'initialize', [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities' => (object) [],
            'clientInfo' => self::CLIENT_INFO,
        ]);

        // Streamable-HTTP servers may issue a session id we must echo back.
        // It arrives as a response header.
        if ($initResponse['_session_id']) {
            $sessionId = $initResponse['_session_id'];
        }

        $this->notify($server, $sessionId, 'notifications/initialized');

        $listResponse = $this->rpc($server, $sessionId, 'tools/list', (object) []);

        $tools = $listResponse['body']['result']['tools'] ?? null;

        if (! is_array($tools)) {
            throw new McpException('MCP server returned a malformed tools/list response.');
        }

        $this->closeSession($server, $sessionId);

        return $tools;
    }

    /**
     * Call a tool. Returns the result `content` array exactly as the server
     * emits it; the caller is responsible for flattening to a string the LLM
     * can ingest.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function callTool(AgentMcpServer $server, string $name, array $arguments): array
    {
        $this->ensureFreshToken($server);

        $sessionId = null;

        $initResponse = $this->rpc($server, $sessionId, 'initialize', [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities' => (object) [],
            'clientInfo' => self::CLIENT_INFO,
        ]);

        if ($initResponse['_session_id']) {
            $sessionId = $initResponse['_session_id'];
        }

        $this->notify($server, $sessionId, 'notifications/initialized');

        $callResponse = $this->rpc($server, $sessionId, 'tools/call', [
            'name' => $name,
            'arguments' => $arguments,
        ]);

        $result = $callResponse['body']['result'] ?? null;

        if (! is_array($result)) {
            $error = $callResponse['body']['error'] ?? null;
            $message = is_array($error) && isset($error['message'])
                ? (string) $error['message']
                : 'tool/call did not return a result';
            throw new McpException($message);
        }

        $this->closeSession($server, $sessionId);

        return $result;
    }

    /**
     * For OAuth-backed connectors, refresh the access token if it is about
     * to expire so the upcoming JSON-RPC request goes out with a valid
     * bearer. For token-paste connectors this is a no-op.
     */
    private function ensureFreshToken(AgentMcpServer $server): void
    {
        if (! $server->accessTokenIsStale()) {
            return;
        }

        try {
            app(McpOAuthFlow::class)->refresh($server);
            $server->refresh();
        } catch (Throwable $e) {
            // Don't hard-fail here — let the upstream return 401 so the
            // user-facing surface (last_error on the row) shows the real
            // upstream message instead of our refresh guess.
            Log::warning('MCP token refresh failed', [
                'server_id' => $server->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build a fresh PendingRequest for a single MCP call. Always builds anew
     * so headers can't accumulate across calls — Laravel's withHeaders uses
     * array_merge_recursive, which would nest repeated keys into arrays
     * (sending the same header twice on the wire). Notion in particular
     * treats two `Mcp-Session-Id` headers as a comma-joined value and
     * 404s the request as "Session not found".
     */
    private function request(AgentMcpServer $server, ?string $sessionId): PendingRequest
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json, text/event-stream',
            'MCP-Protocol-Version' => self::PROTOCOL_VERSION,
        ];

        foreach ((array) ($server->headers ?? []) as $h) {
            if (isset($h['key'], $h['value'])) {
                $headers[$h['key']] = $h['value'];
            }
        }

        if ($server->auth_type === 'bearer' && $server->auth_value) {
            $headers['Authorization'] = 'Bearer '.$server->auth_value;
        }
        if ($server->auth_type === 'header' && $server->auth_value) {
            $headers['X-Auth'] = $server->auth_value;
        }

        if ($sessionId) {
            $headers['Mcp-Session-Id'] = $sessionId;
        }

        return Http::timeout(max(1, (int) ceil($server->timeout_ms / 1000)))
            ->withHeaders($headers);
    }

    /**
     * Send a JSON-RPC request and return the parsed envelope.
     *
     * @param  array<string, mixed>|object  $params
     * @return array{body: array<string, mixed>, _session_id: ?string}
     */
    private function rpc(
        AgentMcpServer $server,
        ?string $sessionId,
        string $method,
        array|object $params,
    ): array {
        $id = (string) Str::uuid();

        try {
            $response = $this->request($server, $sessionId)
                ->post($server->url, [
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'method' => $method,
                    'params' => $params,
                ]);
        } catch (Throwable $e) {
            throw new McpException('Could not reach MCP server: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            throw new McpException(sprintf(
                'MCP server returned HTTP %d: %s',
                $response->status(),
                mb_substr((string) $response->body(), 0, 200),
            ));
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
            throw new McpException('MCP error from '.$method.': '.(string) $body['error']['message']);
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
     */
    private function notify(AgentMcpServer $server, ?string $sessionId, string $method, array|object $params = []): void
    {
        try {
            $this->request($server, $sessionId)
                ->post($server->url, [
                    'jsonrpc' => '2.0',
                    'method' => $method,
                    'params' => $params,
                ]);
        } catch (Throwable) {
            // Notifications are best-effort.
        }
    }

    private function closeSession(AgentMcpServer $server, ?string $sessionId): void
    {
        if (! $sessionId) {
            return;
        }

        // Streamable HTTP servers expect DELETE on the same endpoint with
        // the session header to release server-side state.
        try {
            $this->request($server, $sessionId)->delete($server->url);
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
        $contentType = strtolower((string) $response->header('Content-Type'));

        if (str_contains($contentType, 'text/event-stream')) {
            $body = (string) $response->body();
            $dataLines = [];
            foreach (preg_split('/\r?\n/', $body) as $line) {
                if (str_starts_with($line, 'data:')) {
                    $dataLines[] = ltrim(substr($line, 5));
                }
            }
            if ($dataLines === []) {
                return null;
            }
            $decoded = json_decode(implode('', $dataLines), true);

            return is_array($decoded) ? $decoded : null;
        }

        $decoded = $response->json();

        return is_array($decoded) ? $decoded : null;
    }
}
