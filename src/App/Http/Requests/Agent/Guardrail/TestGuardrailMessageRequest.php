<?php

declare(strict_types=1);

namespace App\Http\Requests\Agent\Guardrail;

/**
 * Validates "Test a message" on the Guardrails page: the sample message plus
 * the form's current settings, checked like a save. The route enforces
 * `agent.permission:agent.guardrails.manage` and a rate limit.
 */
class TestGuardrailMessageRequest extends SaveGuardrailSettingsRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'message' => ['required', 'string', 'max:2000'],
        ];
    }
}
