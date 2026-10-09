<?php

namespace Domain\Guardrail\Actions;

use Domain\Agent\Models\Agent;
use Domain\Guardrail\Data\GuardrailSettingsData;
use Illuminate\Support\Facades\DB;

/**
 * Saves an agent's guardrail settings (the `guardrails` settings group) in one
 * transaction, so the guards never run on a half-saved configuration.
 */
class SaveGuardrailSettingsAction
{
    public function execute(Agent $agent, GuardrailSettingsData $settings): void
    {
        DB::transaction(function () use ($agent, $settings): void {
            foreach ($settings->toSettings() as $key => $value) {
                $agent->setSetting(key: $key, value: $value, group: 'guardrails');
            }
        });
    }
}
