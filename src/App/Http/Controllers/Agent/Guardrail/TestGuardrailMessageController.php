<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agent\Guardrail;

use App\Http\Requests\Agent\Guardrail\TestGuardrailMessageRequest;
use Domain\Agent\Models\Agent;
use Domain\Guardrail\Actions\TestGuardrailMessageAction;
use Domain\Guardrail\Data\GuardrailTestResultData;
use Illuminate\Http\JsonResponse;

/**
 * "Test a message" on the Guardrails page: runs the input guards over a
 * sample with the form's settings and returns what the visitor would get and
 * which guards fired. Nothing is saved or logged.
 */
class TestGuardrailMessageController
{
    public function __invoke(TestGuardrailMessageRequest $request, Agent $agent, TestGuardrailMessageAction $test): JsonResponse
    {
        $result = $test->execute($agent, $request->toData(), $request->string('message')->toString());

        // An explicit 200: nothing is created, whatever the POST verb says.
        return response()->json(GuardrailTestResultData::fromResult($result));
    }
}
