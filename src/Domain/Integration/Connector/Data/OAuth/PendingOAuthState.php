<?php

namespace Domain\Integration\Connector\Data\OAuth;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * In-flight OAuth state — stashed in the cache by `state` for the few
 * minutes between authorize-redirect and the callback.
 *
 * It carries the PKCE verifier and, for per-workspace OAuth apps, a client
 * secret, so it is stored encrypted ({@see seal()}). `userId` and
 * `sessionBindingHash` tie the state to the account and browser session
 * that started the flow; the callback must present both.
 */
final readonly class PendingOAuthState
{
    public function __construct(
        public int $userId,
        public ?string $connectorSlug,
        public string $authServerUrl,
        public string $tokenEndpoint,
        public string $clientId,
        public string $codeVerifier,
        public string $mcpUrl,
        public string $sessionBindingHash,
        public ?string $clientSecret = null,
        public ?string $scopes = null,
        public ?string $customName = null,
        public ?string $customSlug = null,
    ) {}

    /**
     * Hash of the browser-session secret, so the raw value never sits in the
     * cache next to the state.
     */
    public static function hashBinding(string $sessionBinding): string
    {
        return hash('sha256', $sessionBinding);
    }

    /**
     * True when the given browser-session secret is the one the flow started
     * with.
     */
    public function matchesSession(string $sessionBinding): bool
    {
        return $sessionBinding !== ''
            && hash_equals($this->sessionBindingHash, self::hashBinding($sessionBinding));
    }

    /**
     * Encrypted form for the cache.
     */
    public function seal(): string
    {
        return Crypt::encryptString((string) json_encode($this->toArray()));
    }

    /**
     * Read a cached payload back from its encrypted form; null for anything
     * else (missing, tampered, undecryptable, or without a session binding).
     */
    public static function unseal(mixed $payload): ?self
    {
        if (! is_string($payload)) {
            return null;
        }

        try {
            $payload = json_decode(Crypt::decryptString($payload), true);
        } catch (DecryptException) {
            return null;
        }

        if (! is_array($payload) || ! isset($payload['user_id'], $payload['client_id'], $payload['code_verifier'], $payload['session_binding_hash'])) {
            return null;
        }

        return self::fromArray($payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
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
            'session_binding_hash' => $this->sessionBindingHash,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            userId: (int) $data['user_id'],
            connectorSlug: $data['connector_slug'] ?? null,
            authServerUrl: (string) $data['auth_server_url'],
            tokenEndpoint: (string) $data['token_endpoint'],
            clientId: (string) $data['client_id'],
            codeVerifier: (string) $data['code_verifier'],
            mcpUrl: (string) $data['mcp_url'],
            sessionBindingHash: (string) $data['session_binding_hash'],
            clientSecret: $data['client_secret'] ?? null,
            scopes: $data['scopes'] ?? null,
            customName: $data['custom_name'] ?? null,
            customSlug: $data['custom_slug'] ?? null,
        );
    }
}
