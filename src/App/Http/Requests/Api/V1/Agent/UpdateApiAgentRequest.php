<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Agent;

use Domain\Agent\Data\Account\UpdateApiAgentData;
use Illuminate\Foundation\Http\FormRequest;

class UpdateApiAgentRequest extends FormRequest
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
            // Display name for the agent, shown in the dashboard and chat header.
            'name' => ['sometimes', 'string', 'min:3', 'max:255'],
            // System prompt that defines the agent's behaviour, tone, and rules. Cannot be combined with prompt_id.
            'system_instructions' => ['sometimes', 'nullable', 'string', 'max:10000'],
            // The id of a reusable saved prompt to use instead of system_instructions. Cannot be combined with system_instructions.
            'prompt_id' => ['sometimes', 'nullable', 'string', 'uuid', 'prohibits:system_instructions'],
            // Arbitrary key/value pairs to attach to the agent — for example your own reference ids.
            'metadata' => ['sometimes', 'nullable', 'array'],
            // Whether the agent's chat is publicly reachable without a sign-in link.
            'is_public' => ['sometimes', 'boolean'],
        ];
    }

    public function toData(): UpdateApiAgentData
    {
        return UpdateApiAgentData::from($this->validated());
    }
}
