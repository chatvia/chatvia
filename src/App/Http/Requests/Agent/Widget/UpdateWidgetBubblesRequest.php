<?php

namespace App\Http\Requests\Agent\Widget;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWidgetBubblesRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Access is gated by the route middleware (agent.permission:agent.setup).
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'bubbles' => ['array'],
            'bubbles.*.text' => ['required', 'string', 'max:255'],
            'bubbles.*.overrides' => ['nullable', 'array'],
            'bubbles.*.overrides.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * The validated widget bubbles, ready for SetWidgetBubblesAction.
     *
     * @return array<int, array{text: string, overrides?: array<string, string>}>
     */
    public function bubbles(): array
    {
        return $this->validated('bubbles') ?? [];
    }
}
