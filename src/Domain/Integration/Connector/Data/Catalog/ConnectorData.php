<?php

namespace Domain\Integration\Connector\Data\Catalog;

use Spatie\LaravelData\Attributes\Hidden;
use Spatie\LaravelData\Data;

class ConnectorData extends Data
{
    public function __construct(
        public string $slug,
        public string $name,
        public string $description,
        public string $category,
        public string $icon,
        public string $brandColor,
        public string $authStrategy,
        public string $mcpUrl,
        // Sort key within each provider group — lower comes first. Required.
        public int $priority,
        // 'chatvia' for built-in MCPs we host (weather, currency, …);
        // 'featured' for third-party integrations (Slack, Atlassian, …).
        public string $provider = 'featured',
        public ?string $logoUrl = null,
        public ?string $authType = null,
        public ?string $tokenLabel = null,
        public ?string $tokenHelpUrl = null,
        public ?string $tokenPattern = null,
        public ?string $tokenPlaceholder = null,
        public bool $comingSoon = false,
        // Pre-registered OAuth (services that don't support RFC 7591 DCR —
        // GitHub, Slack, Microsoft, …). All null when DCR is used.
        public ?string $oauthClientId = null,
        // Platform-wide OAuth app secret — server-only. #[Hidden] keeps it out
        // of every JSON/Inertia response while remaining available to the
        // server-side OAuth flow (hasPreRegisteredOAuth() etc.).
        #[Hidden]
        public ?string $oauthClientSecret = null,
        public ?string $oauthAuthorizationEndpoint = null,
        public ?string $oauthTokenEndpoint = null,
        public ?string $oauthScopes = null,
    ) {}

    /**
     * @param  array<string, mixed>  $entry
     */
    public static function fromArray(array $entry): self
    {
        $slug = (string) ($entry['slug'] ?? '');

        if (! isset($entry['priority'])) {
            throw new \InvalidArgumentException("Connector '{$slug}' is missing the required 'priority' key in config/connectors.php.");
        }

        return new self(
            slug: $slug,
            name: (string) $entry['name'],
            description: (string) $entry['description'],
            category: (string) ($entry['category'] ?? 'Other'),
            icon: (string) ($entry['icon'] ?? 'plug'),
            brandColor: (string) ($entry['brand_color'] ?? '6B7280'),
            authStrategy: (string) $entry['auth_strategy'],
            mcpUrl: (string) $entry['mcp_url'],
            priority: (int) $entry['priority'],
            provider: (string) ($entry['provider'] ?? 'featured'),
            logoUrl: $entry['logo_url'] ?? null,
            authType: $entry['auth_type'] ?? null,
            tokenLabel: $entry['token_label'] ?? null,
            tokenHelpUrl: $entry['token_help_url'] ?? null,
            tokenPattern: $entry['token_pattern'] ?? null,
            tokenPlaceholder: $entry['token_placeholder'] ?? null,
            comingSoon: (bool) ($entry['coming_soon'] ?? false),
            oauthClientId: $entry['oauth_client_id'] ?? null,
            oauthClientSecret: $entry['oauth_client_secret'] ?? null,
            oauthAuthorizationEndpoint: $entry['oauth_authorization_endpoint'] ?? null,
            oauthTokenEndpoint: $entry['oauth_token_endpoint'] ?? null,
            oauthScopes: $entry['oauth_scopes'] ?? null,
        );
    }

    /**
     * Whether the catalog entry carries pre-registered OAuth credentials.
     * Used as a fallback when the MCP server's auth-server doesn't expose
     * a `registration_endpoint` (no RFC 7591 DCR).
     */
    public function hasPreRegisteredOAuth(): bool
    {
        return $this->authStrategy === 'oauth'
            && $this->oauthClientId !== null
            && $this->oauthAuthorizationEndpoint !== null
            && $this->oauthTokenEndpoint !== null;
    }
}
