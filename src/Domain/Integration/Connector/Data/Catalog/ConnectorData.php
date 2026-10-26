<?php

namespace Domain\Integration\Connector\Data\Catalog;

use Domain\Integration\Connector\Catalog\Connector;
use Domain\Integration\Connector\Enums\ConnectorAuthStrategy;
use Domain\Integration\Mcp\Enums\McpAuthType;
use Domain\Integration\Mcp\Enums\McpServerCategory;
use Spatie\LaravelData\Attributes\Hidden;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One entry of the connector catalog (a {@see Connector}) as a typed
 * record: how it looks in the UI, how a workspace connects it and, for OAuth
 * presets, the pre-registered client details. Server-only secrets are
 * #[Hidden] so they never reach a JSON or Inertia response.
 */
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
        public ConnectorAuthStrategy $authStrategy,
        public string $mcpUrl,
        // Sort key within each provider group — lower comes first. Required.
        public int $priority,
        // 'chatvia' for built-in MCPs we host (weather, currency, …);
        // 'featured' for third-party integrations (Slack, Atlassian, …).
        public string $provider = 'featured',
        // The McpServerCategory value a connected row carries ('builtin' / 'integration').
        // Null falls back to the provider+auth derivation in serverCategory().
        #[Hidden]
        public ?string $mcpCategory = null,
        public ?string $logoUrl = null,
        // The chat's activity line while one of the connector's tools runs.
        public ?string $activityLabel = null,
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
        // Whether a voice call may offer this connector's tools. False for
        // tools that only make sense on a screen (a chart, a form to fill).
        public bool $voice = true,
        // The connectors this capability works through: the workspace connects
        // one before it can be turned on (Calendar: Calendly or Cal.com).
        /** @var list<string> */
        public array $worksThrough = [],
        // False while the install does not offer it: a capability whose
        // service an admin has not set up (Image generation, Audio generation).
        public bool $available = true,
        // True when the install needs a license key to offer it: all the
        // capabilities do. With `available` false it says why it is off.
        public bool $needsLicense = false,
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
     * Whether the catalog entry carries pre-registered OAuth credentials.
     * Used as a fallback when the MCP server's auth-server doesn't expose
     * a `registration_endpoint` (no RFC 7591 DCR).
     */
    public function hasPreRegisteredOAuth(): bool
    {
        return $this->authStrategy === ConnectorAuthStrategy::Oauth
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

        return $this->provider === 'chatvia' && $this->authStrategy === ConnectorAuthStrategy::None
            ? McpServerCategory::Builtin
            : McpServerCategory::Integration;
    }

    /**
     * How a connected server of this connector authenticates its MCP calls:
     * no credentials for a no-auth preset, otherwise the preset's configured
     * `auth_type` (a bearer token unless the catalog says otherwise).
     */
    public function serverAuthType(): McpAuthType
    {
        if ($this->authStrategy === ConnectorAuthStrategy::None) {
            return McpAuthType::None;
        }

        return McpAuthType::tryFrom($this->authType ?? '') ?? McpAuthType::Bearer;
    }
}
