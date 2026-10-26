<?php

namespace Domain\Form\Actions;

use Domain\Agent\Models\Agent;
use Domain\Form\Models\FormDefinition;
use Domain\Integration\Connector\Actions\Agent\EnableAgentCapabilityAction;
use Domain\Integration\Connector\Exceptions\CapabilityUnavailableException;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Mcp\Exceptions\McpException;

/**
 * A form is useless unless the agent can show it, so adding one turns the
 * Forms capability on for the agent (a no-op once it is on). Connecting the
 * capability syncs its tools remotely; if that fails the form is still saved,
 * the failure is reported, and the owner can enable Forms by hand. Without a
 * license key Forms is unavailable and is simply left off.
 */
class EnableFormsCapabilityAction
{
    public function __construct(
        private readonly ConnectorCatalog $catalog,
        private readonly EnableAgentCapabilityAction $enable,
    ) {}

    public function execute(Agent $agent): void
    {
        $capability = $this->catalog->capabilities()->get(FormDefinition::CAPABILITY_SLUG);

        if ($capability === null || $agent->isFormsEnabled()) {
            return;
        }

        try {
            $this->enable->execute($agent, $capability);
        } catch (CapabilityUnavailableException) {
            // No license key on this install: Forms stays off until one is added.
        } catch (McpException $e) {
            report($e);
        }
    }
}
