<?php

declare(strict_types=1);

namespace App\Http\Requests\Agent\Eval;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the "Generate with AI" request of the Evals "From knowledge"
 * picker: how many questions to write. The route already enforces
 * `agent.permission:agent.evals.manage`, so authorize() defers to it.
 */
class GenerateEvalCaseQuestionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'count' => ['nullable', 'integer', 'min:1', 'max:10'],
        ];
    }

    public function desiredCount(int $default = 5): int
    {
        return (int) ($this->validated('count') ?? $default);
    }
}
