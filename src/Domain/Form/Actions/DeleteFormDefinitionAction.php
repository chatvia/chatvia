<?php

namespace Domain\Form\Actions;

use Domain\Agent\Models\Agent;
use Domain\Form\Models\FormDefinition;
use Domain\Integration\Connector\Actions\Agent\DisableAgentCapabilityAction;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Illuminate\Support\Facades\DB;

/**
 * Delete one of an agent's forms with its leads. The leads go first, in
 * batches, each its own short statement; the foreign key's cascade is only a
 * backstop for a lead written in between. Removing the last form turns the
 * Forms capability back off: there is nothing left for the agent to show.
 * The form's delete and that switch run in one transaction with the agent
 * row locked, so two last forms deleted at once can't each still see the
 * other and leave Forms on with no form.
 */
class DeleteFormDefinitionAction
{
    /**
     * @param  int  $leadBatch  leads per DELETE
     */
    public function __construct(
        private readonly ConnectorCatalog $catalog,
        private readonly DisableAgentCapabilityAction $disable,
        private readonly int $leadBatch = 2000,
    ) {}

    public function execute(Agent $agent, FormDefinition $form): void
    {
        $this->deleteLeads($form);

        DB::transaction(function () use ($agent, $form): void {
            Agent::query()->whereKey($agent->id)->lockForUpdate()->first();

            $form->delete();

            $capability = $this->catalog->capabilities()->get(FormDefinition::CAPABILITY_SLUG);

            if ($capability !== null && FormDefinition::query()->forAgent($agent)->unmanaged()->doesntExist()) {
                $this->disable->execute($agent, $capability);
            }
        });
    }

    /**
     * Every submission of the form (pending ones too), read from the index
     * that starts with form_definition_id. No order is needed: every row goes.
     */
    private function deleteLeads(FormDefinition $form): void
    {
        $sql = "DELETE FROM form_submissions WHERE id IN (SELECT id FROM form_submissions WHERE form_definition_id = ? LIMIT {$this->leadBatch})";

        do {
            $deleted = DB::affectingStatement($sql, [$form->id]);
        } while ($deleted > 0);
    }
}
