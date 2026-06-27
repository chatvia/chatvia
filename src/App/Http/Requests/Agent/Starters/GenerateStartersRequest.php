<?php

namespace App\Http\Requests\Agent\Starters;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the AI starter-generation request. The route already enforces
 * `agent.permission:agent.setup`, so authorize() defers to it.
 */
class GenerateStartersRequest extends FormRequest
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
