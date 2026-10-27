<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Services;

use Domain\Agent\Models\Agent;
use Domain\Integration\Connector\Data\Catalog\ConnectorData;

/**
 * Which connector a capability that works through others uses for an agent
 * (Calendar: Calendly or Cal.com). The agent's choice is kept in its
 * `capabilities` settings, keyed by capability slug; a choice that is no
 * longer connected falls back to the first connected one. A capability that
 * works through nothing is always ready.
 */
class CapabilityProvider
{
    public const string SETTINGS_GROUP = 'capabilities';

    public function __construct(private readonly WorkspaceConnection $connections) {}

    /**
     * The capability's connectors the workspace can use, in catalog order.
     *
     * @return list<string>
     */
    public function connected(Agent $agent, ConnectorData $capability): array
    {
        return $this->connections->connected($agent, $capability->worksThrough);
    }

    /** The connector the capability uses for this agent; null when none is connected. */
    public function providerFor(Agent $agent, ConnectorData $capability): ?string
    {
        $connected = $this->connected($agent, $capability);
        $chosen = $this->chosen($agent, $capability);

        return collect($connected)->containsStrict($chosen) ? $chosen : ($connected[0] ?? null);
    }

    public function isMet(Agent $agent, ConnectorData $capability): bool
    {
        return $capability->worksThrough === [] || $this->providerFor($agent, $capability) !== null;
    }

    /** The connector the agent's owner picked, connected or not. */
    public function chosen(Agent $agent, ConnectorData $capability): ?string
    {
        $value = $agent->settings(self::SETTINGS_GROUP)->get($capability->slug);

        return filled($value) ? (string) $value : null;
    }
}
