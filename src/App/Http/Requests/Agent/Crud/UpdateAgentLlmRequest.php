<?php

namespace App\Http\Requests\Agent\Crud;

use Closure;
use Domain\Agent\Data\Settings\UpdateAgentLlmData;
use Domain\Agent\Models\Agent;
use Domain\Llm\Models\Llm;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the agent's model choice and its sampling settings (temperature,
 * top-k, top-p, presence and frequency penalty). The model must be one that
 * may be picked (it and its provider are on), or the agent's current one.
 * Agent access and the `agent.setup` permission are checked by the route
 * middleware.
 */
class UpdateAgentLlmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Agent $agent */
        $agent = $this->route('agent');

        return [
            'model_external_name' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail) use ($agent): void {
                $model = Llm::catalog()->firstWhere('external_name', $value);

                if (! $model instanceof Llm || (! $model->isAvailable() && $model->id !== $agent->llm_id)) {
                    $fail(__('Pick one of the available models.'));
                }
            }],
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
