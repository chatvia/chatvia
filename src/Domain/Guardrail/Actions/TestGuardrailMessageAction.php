<?php

declare(strict_types=1);

namespace Domain\Guardrail\Actions;

use Domain\Agent\Models\Agent;
use Domain\Guardrail\Data\GuardrailSettingsData;
use Domain\Guardrail\Data\GuardResultData;
use Domain\Guardrail\Services\GuardrailService;

/**
 * Runs the input guards over a sample message with the settings on the
 * Guardrails page, saved or not, so the owner sees what a visitor would get
 * before changing the live agent. Nothing is stored or logged: the result
 * goes back to the page only.
 */
class TestGuardrailMessageAction
{
    public function __construct(
        private readonly GuardrailService $guardrails,
    ) {}

    public function execute(Agent $agent, GuardrailSettingsData $settings, string $message): GuardResultData
    {
        return $this->guardrails->inspectInputWith($agent, $settings, $message);
    }
}
