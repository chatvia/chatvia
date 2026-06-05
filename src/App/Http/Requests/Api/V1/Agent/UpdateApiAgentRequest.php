<?php

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
            'name' => ['sometimes', 'string', 'min:3', 'max:255'],
            'system_instructions' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'prompt_id' => ['sometimes', 'nullable', 'string', 'uuid', 'prohibits:system_instructions'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'is_public' => ['sometimes', 'boolean'],
        ];
    }

    public function toData(): UpdateApiAgentData
    {
        return UpdateApiAgentData::from($this->validated());
    }
}
