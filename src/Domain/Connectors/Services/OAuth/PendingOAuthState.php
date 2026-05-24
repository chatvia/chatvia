<?php

namespace Domain\Connectors\Services\OAuth;

/**
 * In-flight OAuth state — stashed in the cache by `state` for the few
 * seconds between authorize-redirect and the callback.
 */
class PendingOAuthState
{
    public function __construct(
        public readonly int $agentId,
        public readonly ?string $connectorSlug,
        public readonly string $authServerUrl,
        public readonly string $tokenEndpoint,
        public readonly string $clientId,
        public readonly string $codeVerifier,
        public readonly string $mcpUrl,
        public readonly ?string $clientSecret = null,
        public readonly ?string $scopes = null,
        public readonly ?string $customName = null,
        public readonly ?string $customSlug = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'agent_id' => $this->agentId,
            'connector_slug' => $this->connectorSlug,
            'auth_server_url' => $this->authServerUrl,
            'token_endpoint' => $this->tokenEndpoint,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code_verifier' => $this->codeVerifier,
            'mcp_url' => $this->mcpUrl,
            'scopes' => $this->scopes,
            'custom_name' => $this->customName,
            'custom_slug' => $this->customSlug,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            agentId: (int) $data['agent_id'],
            connectorSlug: $data['connector_slug'] ?? null,
            authServerUrl: (string) $data['auth_server_url'],
            tokenEndpoint: (string) $data['token_endpoint'],
            clientId: (string) $data['client_id'],
            codeVerifier: (string) $data['code_verifier'],
            mcpUrl: (string) $data['mcp_url'],
            clientSecret: $data['client_secret'] ?? null,
            scopes: $data['scopes'] ?? null,
            customName: $data['custom_name'] ?? null,
            customSlug: $data['custom_slug'] ?? null,
        );
    }
}
