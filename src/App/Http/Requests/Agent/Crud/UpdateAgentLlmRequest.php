<?php

namespace App\Http\Requests\Agent\Crud;

use Closure;
use Domain\Agent\Data\Settings\UpdateAgentLlmData;
use Domain\Agent\Models\Agent;
use Domain\Agent\Services\OfferedMediaModels;
use Domain\Conversation\Enums\GeneratedMediaKind;
use Domain\Llm\Enums\ReasoningEffort;
use Domain\Llm\Models\Llm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the agent's model choice, its sampling settings (temperature,
 * top-k, top-p, presence and frequency penalty), its reasoning effort and
 * the models it offers chatters, and whether chatters may pick the image
 * and the video model. The model, and each offered one, must be one that
 * may be picked (it and its provider are on), or one the agent already
 * uses. A new effort other than Off, and turning a chatters' choice on, are
 * refused while the admin does not allow them. Agent access
 * and the `agent.setup` permission are checked by the route middleware.
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
            'model_id' => ['required', 'integer', function (string $attribute, mixed $value, Closure $fail) use ($agent): void {
                $model = Llm::catalog()->firstWhere('id', (int) $value);

                if (! $model instanceof Llm || (! $model->isAvailable() && $model->id !== $agent->llm_id)) {
                    $fail(__('Pick one of the available models.'));
                }
            }],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'top_k' => ['required', 'integer', 'min:0', 'max:100'],
            'top_p' => ['required', 'numeric', 'min:0', 'max:1'],
            'presence_penalty' => ['required', 'numeric', 'min:-2', 'max:2'],
            'frequency_penalty' => ['required', 'numeric', 'min:-2', 'max:2'],
            // While reasoning is off for the install, the effort can only be
            // set to Off or kept as it is (it then waits, unused).
            'reasoning_effort' => ['sometimes', Rule::enum(ReasoningEffort::class), function (string $attribute, mixed $value, Closure $fail) use ($agent): void {
                $kept = $value === (string) $agent->settings('reasoning')->get('effort');

                if ($value !== ReasoningEffort::Off->value && ! $kept && ! (bool) config('chatvia.ai.reasoning.enabled')) {
                    $fail(__('Your admin has turned reasoning off.'));
                }
            }],
            // While the admin does not let agents offer a choice, it can only
            // be turned off or kept as it is (it then waits, unused).
            'model_choice_enabled' => ['sometimes', 'boolean', function (string $attribute, mixed $value, Closure $fail) use ($agent): void {
                $kept = (bool) $agent->settings('model_choice')->get('enabled');

                if ($this->boolean('model_choice_enabled') && ! $kept && ! (bool) config('chatvia.ai.chatter_model_choice')) {
                    $fail(__('Your admin does not let chatters pick a model.'));
                }
            }],
            'image_model_choice_enabled' => ['sometimes', 'boolean', $this->mediaChoiceRule(GeneratedMediaKind::Image)],
            'video_model_choice_enabled' => ['sometimes', 'boolean', $this->mediaChoiceRule(GeneratedMediaKind::Video)],
            'offered_model_ids' => ['sometimes', 'array', 'max:20'],
            'offered_model_ids.*' => ['integer', 'distinct', function (string $attribute, mixed $value, Closure $fail) use ($agent): void {
                $model = Llm::catalog()->firstWhere('id', (int) $value);
                $kept = collect((array) $agent->settings('model_choice')->get('models'))->contains((int) $value);

                if (! $model instanceof Llm || (! $model->isAvailable() && ! $kept)) {
                    $fail(__('Pick one of the available models.'));
                }
            }],
        ];
    }

    public function toData(): UpdateAgentLlmData
    {
        return new UpdateAgentLlmData(
            model_id: $this->integer('model_id'),
            temperature: (float) $this->input('temperature'),
            top_k: (int) $this->input('top_k'),
            top_p: (float) $this->input('top_p'),
            presence_penalty: (float) $this->input('presence_penalty'),
            frequency_penalty: (float) $this->input('frequency_penalty'),
            reasoning_effort: $this->enum('reasoning_effort', ReasoningEffort::class) ?? ReasoningEffort::Off,
            model_choice_enabled: $this->boolean('model_choice_enabled'),
            offered_model_ids: collect((array) $this->input('offered_model_ids', []))->map(fn (mixed $id): int => (int) $id)->values()->all(),
            image_model_choice_enabled: $this->boolean('image_model_choice_enabled'),
            video_model_choice_enabled: $this->boolean('video_model_choice_enabled'),
        );
    }

    /**
     * While the admin does not let agents offer a choice of image or video
     * models, it can only be turned off or kept as it is (it then waits, unused).
     */
    private function mediaChoiceRule(GeneratedMediaKind $kind): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($kind): void {
            /** @var Agent $agent */
            $agent = $this->route('agent');
            $offered = app(OfferedMediaModels::class);

            if ($this->boolean($attribute) && ! $offered->isEnabled($agent, $kind) && ! $offered->isAllowed($kind)) {
                $fail(__('Your admin does not let agents offer a choice of models.'));
            }
        };
    }
}
