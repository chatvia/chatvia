<?php

namespace Domain\Integration\Connector\Services\OAuth;

use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Support\Http\SafeUrl;
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
        $authServers = $this->fetchProtectedResource($this->resourceMetadataCandidates($mcpUrl));

        if ($authServers === []) {
            throw new McpOAuthException('The MCP server did not advertise any authorization servers.');
        }

        // First-server-wins. The spec allows multiple but in practice the
        // first entry is the canonical one.
        return $this->fetchAuthServerMetadata($authServers[0], $connector);
    }

    /**
     * SSRF-guarded GET. Every URL in the discovery cascade is upstream- or
     * user-controlled (the entry URL, the WWW-Authenticate resource_metadata
     * URL, each authorization_servers[] entry), so each hop is re-validated
     * against private/reserved ranges and redirects are disabled.
     */
    private function safeGet(string $url): Response
    {
        SafeUrl::assert($url);

        return Http::withOptions(SafeUrl::clientOptions())
            ->withHeaders(['Accept' => 'application/json'])
            ->get($url);
    }

    /**
     * Build the ordered list of protected-resource metadata URLs to try:
     *
     *   1. The exact URL the server names in its `WWW-Authenticate` challenge
     *      (RFC 9728 §5.1) — authoritative when present.
     *   2. The RFC 9728 §3.1 well-known URL with the resource's path inserted
     *      after `/.well-known/oauth-protected-resource`. Path-scoped servers
     *      (e.g. Zapier at /api/mcp/mcp) publish here and do NOT emit the
     *      challenge hint, so the root form below 404s for them.
     *   3. The path-less well-known URL at the origin root.
     *
     * @return array<int, string>
     */
    private function resourceMetadataCandidates(string $mcpUrl): array
    {
        $candidates = [];

        try {
            $header = $this->safeGet($mcpUrl)->header('WWW-Authenticate');
            if ($header && preg_match('/resource_metadata="?([^",\s]+)"?/i', $header, $m)) {
                $candidates[] = $m[1];
            }
        } catch (Throwable) {
            // Best-effort: even an unreachable probe still has predictable
            // well-known URLs worth trying below.
        }

        $origin = rtrim($this->originOf($mcpUrl), '/');
        $path = rtrim((string) (parse_url($mcpUrl, PHP_URL_PATH) ?: ''), '/');

        if ($path !== '') {
            $candidates[] = $origin.'/.well-known/oauth-protected-resource'.$path;
        }
        $candidates[] = $origin.'/.well-known/oauth-protected-resource';

        return array_values(array_unique($candidates));
    }

    /**
     * Try each candidate protected-resource URL in order, returning the
     * `authorization_servers` from the first that resolves. Throws with the
     * last failure only if none do.
     *
     * @param  array<int, string>  $candidateUrls
     * @return array<int, string>
     */
    private function fetchProtectedResource(array $candidateUrls): array
    {
        $lastError = 'Could not fetch protected-resource metadata.';

        foreach ($candidateUrls as $url) {
            try {
                $response = $this->safeGet($url);
            } catch (Throwable $e) {
                $lastError = 'Could not fetch protected-resource metadata: '.$e->getMessage();

                continue;
            }

            if ($response->failed()) {
                $lastError = "Protected-resource metadata request failed ({$response->status()}).";

                continue;
            }

            $body = $response->json();
            if (! is_array($body) || ! isset($body['authorization_servers']) || ! is_array($body['authorization_servers'])) {
                $lastError = 'Protected-resource metadata did not list authorization_servers.';

                continue;
            }

            $servers = array_values(array_filter($body['authorization_servers'], 'is_string'));
            if ($servers !== []) {
                return $servers;
            }
            $lastError = 'Protected-resource metadata did not list authorization_servers.';
        }

        throw new McpOAuthException($lastError);
    }

    private function fetchAuthServerMetadata(string $authServerUrl, ?ConnectorData $connector): OAuthServerMetadata
    {
        $lastStatus = null;
        foreach ($this->candidateMetadataUrls($authServerUrl) as $url) {
            try {
                $response = $this->safeGet($url);
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
        if ($connector instanceof ConnectorData && $connector->hasPreRegisteredOAuth()) {
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
