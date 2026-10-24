<?php

namespace App\Http\Requests\Agent\Eval;

use Domain\Evaluation\Data\CreateEvalCasesFromQuestionsData;
use Domain\Evaluation\Data\KnowledgeQuestionItemData;
use Domain\Evaluation\Enums\EvalQuestionSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Validates a bulk "From knowledge" selection: each item references a chunk or
 * QA pair by id plus the picked question text. The 255-char cap exists because
 * the question doubles as the case name (a 255-char column); the 200-item cap
 * matches the picker's candidate limit. The route already enforces
 * `agent.permission:agent.evals.manage`, so authorize() defers to the middleware.
 */
class CreateEvalCasesFromKnowledgeRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.type' => ['required', Rule::enum(EvalQuestionSource::class)],
            'items.*.id' => ['required', 'integer', 'min:1'],
            'items.*.question' => ['required', 'string', 'max:255'],
        ];
    }

    public function toData(): CreateEvalCasesFromQuestionsData
    {
        /** @var list<array{type: string, id: int|string, question: string}> $items */
        $items = $this->validated('items');

        return new CreateEvalCasesFromQuestionsData(
            items: array_map(
                fn (array $item): KnowledgeQuestionItemData => new KnowledgeQuestionItemData(
                    type: EvalQuestionSource::from($item['type']),
                    id: (int) $item['id'],
                    question: Str::trim($item['question']),
                ),
                $items,
            ),
        );
    }
}
