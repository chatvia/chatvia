<?php

namespace Domain\Integration\Connector\Data\Catalog;

use Domain\Integration\Mcp\Enums\McpServerCategory;
use Spatie\LaravelData\Attributes\Hidden;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
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
        // Explicit McpServerCategory value from config ('builtin' / 'integration').
        // Null falls back to the provider+auth derivation in serverCategory().
        #[Hidden]
        public ?string $mcpCategory = null,
        public ?string $logoUrl = null,
        public ?string $authType = null,
        public ?string $tokenLabel = null,
        public ?string $tokenHelpUrl = null,
        public ?string $tokenPattern = null,
        public ?string $tokenPlaceholder = null,
        // When true, the catalog has no fixed `mcpUrl` and the user supplies
        // their own MCP server URL at connect time (token strategy only). Used
        // for services without a first-party hosted MCP server (e.g. Zendesk).
        public bool $requiresUrl = false,
        public bool $comingSoon = false,
        // When true, this preset is presented on the per-agent Capabilities
        // page (first-party, no-auth chat enrichments like Interactive Charts)
        // instead of the Connectors list. Purely a UI-surface flag — the MCP
        // server it provisions is identical to any other builtin connector.
        public bool $isCapability = false,
        // Surfaces this connector's tools are allowed on — a list of
        // ConversationSource values (e.g. ['hosted_chat']). Null (the default)
        // means every surface. The tool router withholds the connector's tools
        // from the model on any surface not in this list (e.g. Interactive
        // Charts only renders on Hosted Chat).
        /** @var array<int, string>|null */
        public ?array $surfaces = null,
        // When true, this is an OAuth connector whose client is registered
        // per-workspace (no global app / no DCR) — the workspace supplies its
        // own client_id at connect time (e.g. Salesforce External Client App).
        // Drives a "configure OAuth client" modal instead of a direct redirect.
        public bool $requiresClientId = false,
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
            mcpCategory: $entry['server_category'] ?? null,
            logoUrl: $entry['logo_url'] ?? null,
            authType: $entry['auth_type'] ?? null,
            tokenLabel: $entry['token_label'] ?? null,
            tokenHelpUrl: $entry['token_help_url'] ?? null,
            tokenPattern: $entry['token_pattern'] ?? null,
            tokenPlaceholder: $entry['token_placeholder'] ?? null,
            requiresUrl: (bool) ($entry['requires_url'] ?? false),
            comingSoon: (bool) ($entry['coming_soon'] ?? false),
            isCapability: (bool) ($entry['is_capability'] ?? false),
            surfaces: isset($entry['surfaces']) ? array_values((array) $entry['surfaces']) : null,
            requiresClientId: (bool) ($entry['requires_client_id'] ?? false),
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

    /**
     * The {@see McpServerCategory} a connected row of this connector carries.
     * Chatvia-hosted presets with no external auth (Leads, Interactive Charts)
     * are Builtin; everything else connects to an external service → Integration.
     */
    public function serverCategory(): McpServerCategory
    {
        if ($this->mcpCategory !== null) {
            return McpServerCategory::from($this->mcpCategory);
        }

        return $this->provider === 'chatvia' && $this->authStrategy === 'none'
            ? McpServerCategory::Builtin
            : McpServerCategory::Integration;
    }
}
