<?php

namespace App\Http\Requests\Agent\Source\Qa;

use Domain\Knowledge\Source\Qa\Data\QaEntryData;

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

    protected function qaEntryData(): QaEntryData
    {
        return new QaEntryData(
            title: (string) $this->validated('title'),
            questions: array_values(array_map(
                static fn (array $item): string => (string) ($item['question'] ?? ''),
                (array) $this->validated('questions'),
            )),
            answer: (string) $this->validated('answer'),
        );
    }
}
