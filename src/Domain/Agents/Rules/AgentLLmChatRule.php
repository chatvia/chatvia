<?php

namespace Domain\Agents\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AgentLLmChatRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $agent = current_agent();

        if (! $agent) {
            $fail('No agent context available.');

            return;
        }

        $allowedModels = $agent->llmsFormatted();

        if (! $allowedModels || $allowedModels->isEmpty()) {
            $fail('No models are available for this agent.');

            return;
        }

        $allowedModelIds = $allowedModels->pluck('id')->toArray();

        if (! in_array($value, $allowedModelIds, true)) {
            $fail('The selected model is not available for this agent.');
        }
    }
}
