<?php

namespace App\Http\Requests\Agent\Eval;

use Domain\Evaluation\Data\SaveEvalCaseData;
use Domain\Evaluation\Enums\ScorerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates create/update of one eval case. Scorer-specific config is required
 * conditionally (regex needs a pattern, llm_judge needs criteria, exact needs a
 * reference answer, contains needs a substring). The route already enforces
 * `agent.permission:agent.evals.manage`, so authorize() defers to the middleware.
 */
class SaveEvalCaseRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'input' => ['required', 'string', 'max:8000'],
            'scorer_type' => ['required', Rule::enum(ScorerType::class)],
            'scorer_config' => ['nullable', 'array'],
            'scorer_config.value' => ['nullable', 'required_if:scorer_type,contains', 'string', 'max:2000'],
            'scorer_config.case_sensitive' => ['nullable', 'boolean'],
            'scorer_config.pattern' => ['nullable', 'required_if:scorer_type,regex', 'string', 'max:2000'],
            'scorer_config.criteria' => ['nullable', 'required_if:scorer_type,llm_judge', 'string', 'max:4000'],
            'scorer_config.required' => ['nullable', 'array'],
            'scorer_config.required.*' => ['string', 'max:128'],
            'expected_output' => ['nullable', 'required_if:scorer_type,exact', 'string', 'max:8000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function toData(): SaveEvalCaseData
    {
        /** @var array<string, mixed>|null $config */
        $config = $this->input('scorer_config') ?: null;

        return new SaveEvalCaseData(
            name: $this->string('name')->toString(),
            input: $this->string('input')->toString(),
            scorer_type: ScorerType::from($this->string('scorer_type')->toString()),
            scorer_config: $config,
            expected_output: $this->string('expected_output')->toString() ?: null,
            active: $this->boolean('active', true),
        );
    }
}
