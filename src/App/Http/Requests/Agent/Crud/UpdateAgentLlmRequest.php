<?php

namespace App\Http\Requests\Agent\Crud;

use Domain\Agent\Data\Settings\UpdateAgentLlmData;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAgentLlmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'model_external_name' => ['required', 'string', 'exists:llms,external_name'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'top_k' => ['required', 'integer', 'min:0', 'max:100'],
            'top_p' => ['required', 'numeric', 'min:0', 'max:1'],
            'presence_penalty' => ['required', 'numeric', 'min:-2', 'max:2'],
            'frequency_penalty' => ['required', 'numeric', 'min:-2', 'max:2'],
        ];
    }

    public function toData(): UpdateAgentLlmData
    {
        return new UpdateAgentLlmData(
            model_external_name: $this->string('model_external_name')->toString(),
            temperature: (float) $this->input('temperature'),
            top_k: (int) $this->input('top_k'),
            top_p: (float) $this->input('top_p'),
            presence_penalty: (float) $this->input('presence_penalty'),
            frequency_penalty: (float) $this->input('frequency_penalty'),
        );
    }
}
