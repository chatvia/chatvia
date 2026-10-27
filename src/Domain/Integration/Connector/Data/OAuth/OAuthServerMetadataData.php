<?php

namespace Domain\Integration\Connector\Data\OAuth;

/**
 * RFC 8414 — OAuth 2.0 Authorization Server Metadata.
 *
 * Carries only the fields the MCP OAuth flow reads; everything else the
 * server declares is ignored.
 */
final readonly class OAuthServerMetadataData
{
    /**
     * @param  array<int, string>  $codeChallengeMethodsSupported
     */
    public function __construct(
        public string $authServerUrl,
        public string $authorizationEndpoint,
        public string $tokenEndpoint,
        public ?string $registrationEndpoint,
        public array $codeChallengeMethodsSupported,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(string $authServerUrl, array $payload): self
    {
        return new self(
            authServerUrl: $authServerUrl,
            authorizationEndpoint: (string) ($payload['authorization_endpoint'] ?? ''),
            tokenEndpoint: (string) ($payload['token_endpoint'] ?? ''),
            registrationEndpoint: $payload['registration_endpoint'] ?? null,
            // Absent means "not advertised", which counts as no PKCE support
            // (the MCP authorization spec requires clients to refuse then).
            codeChallengeMethodsSupported: array_values(array_map(
                strval(...),
                array_filter((array) ($payload['code_challenge_methods_supported'] ?? []), is_scalar(...)),
            )),
        );
    }

    public function supportsPkce(): bool
    {
        return in_array('S256', $this->codeChallengeMethodsSupported, true);
    }

    public function supportsDynamicRegistration(): bool
    {
        return filled($this->registrationEndpoint);
    }
}
