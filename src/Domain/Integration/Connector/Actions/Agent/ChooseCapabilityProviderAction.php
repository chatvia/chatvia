<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Actions\Agent;

use Domain\Agent\Models\Agent;
use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Connector\Exceptions\CapabilityProviderUnavailableException;
use Domain\Integration\Connector\Services\CapabilityProvider;

/**
 * Pick which connector a capability works through for one agent (Calendar:
 * Calendly or Cal.com). Only a connector the capability works through and the
 * workspace has connected can be picked. The choice is an agent setting, so a
 * calendar already shown keeps the scheduler it was shown with.
 */
class ChooseCapabilityProviderAction
{
    public function __construct(private readonly CapabilityProvider $providers) {}

    /**
     * @throws CapabilityProviderUnavailableException
     */
    public function execute(Agent $agent, ConnectorData $capability, string $provider): void
    {
        if (! in_array($provider, $this->providers->connected($agent, $capability), true)) {
            throw new CapabilityProviderUnavailableException;
        }

        $agent->setSetting(key: $capability->slug, value: $provider, group: CapabilityProvider::SETTINGS_GROUP);
    }
}
