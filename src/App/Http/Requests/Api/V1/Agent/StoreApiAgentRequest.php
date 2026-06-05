<?php

namespace App\Http\Requests\Api\V1\Agent;

use Domain\Agent\Data\Account\CreateApiAgentData;
use Illuminate\Foundation\Http\FormRequest;

class StoreApiAgentRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'team_id' => ['required', 'string', 'uuid'],
            'system_instructions' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'prompt_id' => ['sometimes', 'nullable', 'string', 'uuid', 'prohibits:system_instructions'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }

    public function toData(): CreateApiAgentData
    {
        return CreateApiAgentData::from($this->validated());
    }
}
