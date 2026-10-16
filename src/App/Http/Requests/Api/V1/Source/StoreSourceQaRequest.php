<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Source;

use Domain\Knowledge\Source\Qa\Data\QaEntryData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A curated question-and-answer entry added over the public API. Unlike the
 * dashboard's form, `questions` is a plain array of strings.
 */
class StoreSourceQaRequest extends FormRequest
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
            // A short name for the entry, shown in the dashboard's Q&A list.
            'title' => ['required', 'string', 'max:255'],
            // The phrasings of the question, one string each.
            'questions' => ['required', 'array', 'min:1'],
            'questions.*' => ['required', 'string', 'max:1000'],
            // The answer the agent gives word for word when a visitor asks
            // one of the questions.
            'answer' => ['required', 'string', 'min:10'],
        ];
    }

    public function toData(): QaEntryData
    {
        return new QaEntryData(
            title: (string) $this->validated('title'),
            questions: array_values(array_map(strval(...), (array) $this->validated('questions'))),
            answer: (string) $this->validated('answer'),
        );
    }
}
