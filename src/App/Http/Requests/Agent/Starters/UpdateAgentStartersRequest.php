<?php

namespace App\Http\Requests\Agent\Starters;

use Domain\Agent\Data\Settings\Copy\StarterData;
use Domain\Agent\Data\Settings\Copy\UpdateStartersData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The agent's full, ordered list of conversation starters from Setup →
 * Starters: each with its id (none for a new one), text, optional icon and the
 * owner's manual per-language edits. Authorization is the route's
 * `agent.permission:agent.setup` middleware.
 */
class UpdateAgentStartersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'starters' => ['array'],
            'starters.*.id' => ['nullable', 'string', 'max:64'],
            'starters.*.text' => ['required', 'string', 'max:255'],
            'starters.*.icon' => ['nullable', 'string', 'max:64'],
            'starters.*.overrides' => ['nullable', 'array'],
            'starters.*.overrides.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function toData(): UpdateStartersData
    {
        return new UpdateStartersData(
            starters: array_values(array_map(static fn (array $starter): StarterData => new StarterData(
                id: $starter['id'] ?? null,
                text: (string) $starter['text'],
                icon: $starter['icon'] ?? null,
                overrides: array_filter((array) ($starter['overrides'] ?? []), is_string(...)),
            ), (array) ($this->validated('starters') ?? []))),
        );
    }
}
