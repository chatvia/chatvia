<?php

namespace Domain\Form\Actions;

use Domain\Agent\Models\Agent;
use Domain\Form\Models\FormDefinition;
use Domain\Integration\Connector\Actions\Agent\DisableAgentCapabilityAction;
use Domain\Integration\Connector\Services\ConnectorCatalog;

/**
 * Delete one of an agent's forms. Removing the last one turns the Forms
 * capability back off — there is nothing left for the agent to show.
 */
class DeleteFormDefinitionAction
{
    public function __construct(
        private readonly ConnectorCatalog $catalog,
        private readonly DisableAgentCapabilityAction $disable,
    ) {}

    public function execute(Agent $agent, FormDefinition $form): void
    {
        $form->delete();

        $capability = $this->catalog->capabilities()->get(FormDefinition::CAPABILITY_SLUG);

        if ($capability !== null && FormDefinition::query()->forAgent($agent)->doesntExist()) {
            $this->disable->execute($agent, $capability);
        }
    }
}
