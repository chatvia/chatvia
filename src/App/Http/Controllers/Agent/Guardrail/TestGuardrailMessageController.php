<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agent\Guardrail;

use App\Http\Requests\Agent\Guardrail\TestGuardrailMessageRequest;
use Domain\Agent\Models\Agent;
use Domain\Guardrail\Actions\TestGuardrailMessageAction;
use Domain\Guardrail\Data\GuardrailTestResultData;

/**
 * "Test a message" on the Guardrails page: runs the input guards over a
 * sample with the form's settings and returns what the visitor would get and
 * which guards fired. Nothing is saved or logged.
 */
class TestGuardrailMessageController
{
    public function __invoke(TestGuardrailMessageRequest $request, Agent $agent, TestGuardrailMessageAction $test): GuardrailTestResultData
    {
        $result = $test->execute($agent, $request->toData(), $request->string('message')->toString());

        return GuardrailTestResultData::fromResult($result);
    }
}
