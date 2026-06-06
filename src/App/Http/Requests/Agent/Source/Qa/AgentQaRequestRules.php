<?php

namespace App\Http\Requests\Agent\Source\Qa;

/**
 * Shared rules for the agent-QA create + update FormRequests. Keeping the
 * rule list in one place prevents drift between dashboard store/update and
 * any future API endpoint.
 */
trait AgentQaRequestRules
{
    /**
     * @return array<string, array<int, string>>
     */
    protected function qaRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.question' => ['required', 'string'],
            'answer' => ['required', 'string', 'min:10'],
        ];
    }
}
