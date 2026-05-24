<?php

namespace Domain\Connectors\Services\OAuth;

use Domain\Connectors\Data\ConnectorData;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Resolves the OAuth authorization server that protects an MCP endpoint,
 * following the discovery cascade specified in the MCP authorization spec:
 *
 *   1. Probe the MCP URL — a compliant server responds with
 *      `WWW-Authenticate: Bearer resource_metadata="<url>"` (RFC 9728).
 *   2. Fetch that protected-resource metadata document → it lists one or
 *      more `authorization_servers`.
 *   3. For each one, fetch `<auth_server>/.well-known/oauth-authorization-server`
 *      (RFC 8414) and return the parsed metadata.
 *
 * Servers that don't issue WWW-Authenticate are not in scope here — those
 * are the token-paste connectors (Stripe, GitHub PAT), which never enter
 * this code path.
 */
class McpOAuthDiscoverer
{
    public function discover(string $mcpUrl, ?ConnectorData $connector = null): OAuthServerMetadata
    {
        $resourceMetadataUrl = $this->probeForResourceMetadataUrl($mcpUrl);
        $authServers = $this->fetchProtectedResource($resourceMetadataUrl);

        if ($authServers === []) {
            throw new McpOAuthException('The MCP server did not advertise any authorization servers.');
        }

        // First-server-wins. The spec allows multiple but in practice the
        // first entry is the canonical one.
        return $this->fetchAuthServerMetadata($authServers[0], $connector);
    }

    private function probeForResourceMetadataUrl(string $mcpUrl): string
    {
        try {
            $response = Http::withHeaders(['Accept' => 'application/json'])->get($mcpUrl);
        } catch (Throwable $e) {
            throw new McpOAuthException('Could not reach the MCP server for OAuth discovery: '.$e->getMessage(), previous: $e);
        }

        $header = $response->header('WWW-Authenticate');
        if ($header && preg_match('/resource_metadata="([^"]+)"/i', $header, $m)) {
            return $m[1];
        }

        // Convention fallback: many MCP servers expose the metadata at a
        // predictable path even without the WWW-Authenticate hint.
        $base = rtrim($this->originOf($mcpUrl), '/');

        return $base.'/.well-known/oauth-protected-resource';
    }

    /**
     * @return array<int, string>
     */
    private function fetchProtectedResource(string $url): array
    {
        try {
            $response = Http::withHeaders(['Accept' => 'application/json'])->get($url);
        } catch (Throwable $e) {
            throw new McpOAuthException('Could not fetch protected-resource metadata: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            throw new McpOAuthException("Protected-resource metadata request failed ({$response->status()}).");
        }

        $body = $response->json();
        if (! is_array($body) || ! isset($body['authorization_servers']) || ! is_array($body['authorization_servers'])) {
            throw new McpOAuthException('Protected-resource metadata did not list authorization_servers.');
        }

        return array_values(array_filter($body['authorization_servers'], 'is_string'));
    }

    private function fetchAuthServerMetadata(string $authServerUrl, ?ConnectorData $connector): OAuthServerMetadata
    {
        $lastStatus = null;
        foreach ($this->candidateMetadataUrls($authServerUrl) as $url) {
            try {
                $response = Http::withHeaders(['Accept' => 'application/json'])->get($url);
            } catch (Throwable) {
                continue;
            }

            if ($response->successful()) {
                $body = $response->json();
                if (is_array($body) && ! empty($body['authorization_endpoint']) && ! empty($body['token_endpoint'])) {
                    return OAuthServerMetadata::fromArray($authServerUrl, $body);
                }
            }

            $lastStatus = $response->status();
        }

        // Some providers (notably GitHub) don't expose RFC 8414 metadata at
        // all. If the catalog entry carries pre-registered endpoints,
        // synthesise a metadata object from those.
        if ($connector && $connector->hasPreRegisteredOAuth()) {
            return $this->fallbackFromConnector($authServerUrl, $connector);
        }

        throw new McpOAuthException(
            'Authorization-server metadata request failed'.($lastStatus ? " ({$lastStatus})" : '').'.'
        );
    }

    /**
     * RFC 8414 §3: the metadata is at the path formed by inserting
     * `/.well-known/oauth-authorization-server` between the host and the
     * authorization-server URL's path. We additionally try the older
     * "append" form because some servers still publish there.
     *
     * @return iterable<string>
     */
    private function candidateMetadataUrls(string $authServerUrl): iterable
    {
        $parts = parse_url($authServerUrl);
        if (! $parts || empty($parts['scheme']) || empty($parts['host'])) {
            return [];
        }

        $origin = $parts['scheme'].'://'.$parts['host'];
        if (! empty($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        $path = isset($parts['path']) ? rtrim($parts['path'], '/') : '';

        if ($path === '') {
            return [$origin.'/.well-known/oauth-authorization-server'];
        }

        return [
            // RFC 8414 form — preferred.
            $origin.'/.well-known/oauth-authorization-server'.$path,
            // OpenID-style append (some older deployments publish here).
            $origin.$path.'/.well-known/oauth-authorization-server',
            // Root form as a last resort.
            $origin.'/.well-known/oauth-authorization-server',
        ];
    }

    private function fallbackFromConnector(string $authServerUrl, ConnectorData $connector): OAuthServerMetadata
    {
        return OAuthServerMetadata::fromArray($authServerUrl, [
            'issuer' => $authServerUrl,
            'authorization_endpoint' => (string) $connector->oauthAuthorizationEndpoint,
            'token_endpoint' => (string) $connector->oauthTokenEndpoint,
            'code_challenge_methods_supported' => ['S256'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
        ]);
    }

    private function originOf(string $url): string
    {
        $parts = parse_url($url);
        if (! $parts || empty($parts['scheme']) || empty($parts['host'])) {
            return $url;
        }

        $origin = $parts['scheme'].'://'.$parts['host'];
        if (! empty($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        return $origin;
    }
}
