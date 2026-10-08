<?php

namespace Domain\Integration\Connector\Services\OAuth;

/**
 * RFC 8414 — OAuth 2.0 Authorization Server Metadata.
 *
 * We only carry the fields the MCP OAuth flow actually uses; the raw
 * payload is kept around so the registrar can persist whatever else the
 * server declares.
 */
class OAuthServerMetadata
{
    /**
     * @param  array<string, mixed>  $raw
     * @param  array<int, string>  $codeChallengeMethodsSupported
     * @param  array<int, string>  $grantTypesSupported
     */
    public function __construct(
        public readonly string $issuer,
        public readonly string $authServerUrl,
        public readonly string $authorizationEndpoint,
        public readonly string $tokenEndpoint,
        public readonly ?string $registrationEndpoint,
        public readonly array $codeChallengeMethodsSupported,
        public readonly array $grantTypesSupported,
        public readonly array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(string $authServerUrl, array $payload): self
    {
        return new self(
            issuer: (string) ($payload['issuer'] ?? $authServerUrl),
            authServerUrl: $authServerUrl,
            authorizationEndpoint: (string) ($payload['authorization_endpoint'] ?? ''),
            tokenEndpoint: (string) ($payload['token_endpoint'] ?? ''),
            registrationEndpoint: $payload['registration_endpoint'] ?? null,
            // Absent means "not advertised", which counts as no PKCE support
            // (the MCP authorization spec requires clients to refuse then).
            codeChallengeMethodsSupported: array_values(array_map(
                strval(...),
                array_filter((array) ($payload['code_challenge_methods_supported'] ?? []), 'is_scalar'),
            )),
            grantTypesSupported: (array) ($payload['grant_types_supported'] ?? ['authorization_code', 'refresh_token']),
            raw: $payload,
        );
    }

    public function supportsPkce(): bool
    {
        return in_array('S256', $this->codeChallengeMethodsSupported, true);
    }

    public function supportsDynamicRegistration(): bool
    {
        return is_string($this->registrationEndpoint) && $this->registrationEndpoint !== '';
    }
}
