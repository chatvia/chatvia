<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog;

use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Connector\Enums\ConnectorAuthStrategy;
use Domain\Integration\Connector\Enums\ConnectorCategory;
use Domain\Integration\Mcp\Enums\McpServerCategory;

/**
 * One entry of the connector catalog: a curated, branded wrapper around an
 * MCP server. A new connector extends the base that matches how it connects
 * ({@see DynamicOAuthConnector}, {@see PreRegisteredOAuthConnector},
 * {@see TokenConnector}, …), sets that base's abstract properties with `get`
 * hooks (so they stay read-only) and is listed in `config/connectors.php`.
 * {@see toData()} is the one place that turns a connector into the
 * {@see ConnectorData} the rest of the app reads.
 */
abstract class Connector
{
    /** The stable key stored on connected servers (`connector_slug`). Never rename it. */
    abstract public string $slug { get; }

    /** The brand name shown on the card. */
    abstract public string $name { get; }

    /** One sentence on what the agent can do with it, in English; the dashboard translates it by slug. */
    abstract public string $description { get; }

    abstract public ConnectorCategory $category { get; }

    /** The brand colour, as six hex digits without `#`. */
    abstract public string $brandColor { get; }

    /** Sort key within the provider group: lower comes first. */
    abstract public int $priority { get; }

    /** The MCP endpoint every workspace connects to; empty when the workspace supplies it. */
    abstract public string $mcpUrl { get; }

    abstract public ConnectorAuthStrategy $authStrategy { get; }

    /** The logo, `public/images/logos/connectors/{slug}.svg` unless a connector says otherwise. */
    public string $logoUrl {
        get => '/images/logos/connectors/'.$this->slug.'.svg';
    }

    /**
     * The chat's activity line while one of the connector's tools runs ("Searching
     * the web…"). Set it only when every tool does that one thing; null lets the
     * chat show "Connecting to {name}…".
     */
    public ?string $activityLabel {
        get => null;
    }

    /**
     * The surfaces the tools may run on, as `ConversationSource` values, for tools
     * whose result only some surfaces can show. Null means every surface.
     *
     * @var list<string>|null
     */
    public ?array $surfaces {
        get => null;
    }

    /** Whether a voice call may offer the tools; false for tools that must be seen. */
    public bool $voice {
        get => true;
    }

    /** `chatvia` for servers this app hosts, `featured` for outside services. */
    public string $provider {
        get => 'featured';
    }

    /** The category a connected server of this connector carries. */
    public McpServerCategory $serverCategory {
        get => McpServerCategory::Integration;
    }

    /** Listed but not yet connectable. */
    public bool $comingSoon {
        get => false;
    }

    /**
     * The connector the workspace must connect first, for a capability that
     * works through it (Calendar books through Calendly). Null for none.
     */
    public ?string $requiresConnector {
        get => null;
    }

    /**
     * Whether the install offers it now. False for a capability whose service
     * an admin has not set up (Image generation without an image service):
     * agents cannot turn it on, and its tools are withheld.
     */
    public function isAvailable(): bool
    {
        return true;
    }

    public function toData(): ConnectorData
    {
        return new ConnectorData(...[
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category->value,
            'icon' => $this->slug,
            'brandColor' => $this->brandColor,
            'authStrategy' => $this->authStrategy,
            'mcpUrl' => $this->mcpUrl,
            'priority' => $this->priority,
            'provider' => $this->provider,
            'mcpCategory' => $this->serverCategory->value,
            'logoUrl' => $this->logoUrl,
            'activityLabel' => $this->activityLabel,
            'comingSoon' => $this->comingSoon,
            'surfaces' => $this->surfaces,
            'voice' => $this->voice,
            'requiresConnector' => $this->requiresConnector,
            'available' => $this->isAvailable(),
            ...$this->extraData(),
        ]);
    }

    /**
     * The fields of {@see ConnectorData} that only some bases set (auth,
     * capability), as named constructor arguments.
     *
     * @return array<string, mixed>
     */
    protected function extraData(): array
    {
        return [];
    }
}
