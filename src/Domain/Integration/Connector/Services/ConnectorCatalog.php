<?php

namespace Domain\Integration\Connector\Services;

use Domain\Integration\Connector\Catalog\Connector;
use Domain\Integration\Connector\Catalog\PreRegisteredOAuthConnector;
use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Mcp\Enums\McpServerCategory;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Read-only access to the connector catalog. The {@see Connector} classes
 * listed in `config/connectors.php` and `config/mcp.php` (the first-party
 * builtin servers) are the source of truth; this service hands out typed
 * records and answers the catalog questions the rest of the app asks: which
 * connectors exist, which are builtins, and which URL is a connector's
 * canonical one.
 */
class ConnectorCatalog
{
    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * Every catalog entry — connectors AND capabilities. Prefer the narrower
     * {@see connectors()} / {@see capabilities()} for UI listings.
     *
     * @return Collection<string, ConnectorData>
     */
    public function all(): Collection
    {
        return $this->definitions()
            ->map(fn (Connector $connector): ConnectorData => $connector->toData())
            ->sortBy(fn (ConnectorData $c): int => $c->priority);
    }

    /**
     * The connector classes, keyed by slug. Built on every call (they are
     * cheap) so an OAuth app saved in the admin panel applies at once.
     *
     * @return Collection<string, Connector>
     */
    public function definitions(): Collection
    {
        return collect(config()->array('connectors.catalog', []))
            ->map(fn (string $class): Connector => $this->container->make($class))
            ->keyBy(fn (Connector $connector): string => $connector->slug);
    }

    /**
     * The connectors whose OAuth app an admin registers in Settings → Connectors.
     *
     * @return Collection<string, PreRegisteredOAuthConnector>
     */
    public function preRegisteredOAuth(): Collection
    {
        return $this->definitions()->whereInstanceOf(PreRegisteredOAuthConnector::class);
    }

    /**
     * Catalog entries shown on the Connectors page — everything that is not a
     * first-party Capability (Interactive Charts, …).
     *
     * @return Collection<string, ConnectorData>
     */
    public function connectors(): Collection
    {
        return $this->all()->reject(fn (ConnectorData $c): bool => $c->isCapability);
    }

    /**
     * First-party, no-auth chat enrichments surfaced on the per-agent
     * Capabilities page (Interactive Charts, …).
     *
     * @return Collection<string, ConnectorData>
     */
    public function capabilities(): Collection
    {
        return $this->all()->filter(fn (ConnectorData $c): bool => $c->isCapability);
    }

    public function find(string $slug): ?ConnectorData
    {
        return $this->definitions()->get($slug)?->toData();
    }

    /**
     * Whether a server slug belongs to a first-party builtin (charts, forms,
     * leads). These are safe, no-auth, Chatvia-hosted tools — they run without
     * an approval gate and are excluded from the governance audit. Resolves by
     * slug.
     */
    public function isBuiltinSlug(string $slug): bool
    {
        return $this->find($slug)?->serverCategory() === McpServerCategory::Builtin;
    }

    /**
     * Token + no-auth connectors are connectable via the synchronous
     * "Connect" endpoint. OAuth has its own redirect dance.
     */
    public function findTokenConnector(string $slug): ?ConnectorData
    {
        $connector = $this->find($slug);

        if (! $connector instanceof ConnectorData || $connector->comingSoon) {
            return null;
        }

        return $connector->authStrategy->connectsDirectly() ? $connector : null;
    }

    /**
     * The connector's canonical MCP URL: the connector's own MCP URL, or `{app.url}/mcp/{slug}` for builtins
     * (config/mcp.php). Null when the catalog defines no fixed endpoint —
     * i.e. the workspace supplies the URL at connect time.
     */
    public function canonicalUrl(string $connectorSlug): ?string
    {
        $preset = $this->find($connectorSlug);
        if ($preset instanceof ConnectorData) {
            return $preset->mcpUrl !== '' ? $preset->mcpUrl : null;
        }

        foreach ((array) config('mcp.builtin', []) as $entry) {
            if (($entry['slug'] ?? null) === $connectorSlug) {
                return $this->builtinUrl($connectorSlug);
            }
        }

        return null;
    }

    /**
     * Where a Chatvia-hosted builtin MCP server lives.
     */
    public function builtinUrl(string $slug): string
    {
        return Str::rtrim((string) config('app.url'), '/').'/mcp/'.$slug;
    }

    /**
     * Whether a server with this connector slug + URL may share the central
     * tool catalog: only when the URL is the connector's CANONICAL one. A
     * preset whose URL is workspace-supplied (TokenConnector::$requiresUrl / BYO-OAuth, e.g.
     * Zendesk or Salesforce) is tenant-specific — its tool definitions must
     * stay per-server so one workspace can never write tool text another
     * workspace's agents read.
     */
    public function sharesCentralCatalog(?string $connectorSlug, ?string $url): bool
    {
        if ($connectorSlug === null) {
            return false;
        }

        $canonical = $this->canonicalUrl($connectorSlug);

        return $canonical !== null
            && Str::rtrim($url ?? '', '/') === Str::rtrim($canonical, '/');
    }
}
