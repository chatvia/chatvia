<?php

declare(strict_types=1);

namespace App\Http\Requests\Agent\Widget;

use Domain\Agent\Data\Settings\Copy\BubbleData;
use Domain\Agent\Data\Settings\Copy\UpdateBubblesData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The agent's widget chat-bubble prompts from Setup → Chat Bubbles: each with
 * its text and the owner's manual per-language edits. Authorization is the
 * route's `agent.permission:agent.setup` middleware.
 */
class UpdateWidgetBubblesRequest extends FormRequest
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
            'bubbles' => ['array'],
            'bubbles.*.text' => ['required', 'string', 'max:255'],
            'bubbles.*.overrides' => ['nullable', 'array'],
            'bubbles.*.overrides.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function toData(): UpdateBubblesData
    {
        return new UpdateBubblesData(
            bubbles: array_values(array_map(static fn (array $bubble): BubbleData => new BubbleData(
                text: (string) $bubble['text'],
                overrides: array_filter((array) ($bubble['overrides'] ?? []), filled(...)),
            ), (array) ($this->validated('bubbles') ?? []))),
        );
    }
}
