<?php

declare(strict_types=1);

namespace App\Http\Requests\Agent\ChatLog;

use Domain\Agent\Enums\UnansweredFilter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The filters of the "Unanswered" list: which replies (`filter`, all by
 * default), whether to keep the ones staff already revised, and the page.
 * Access to the agent and its chat log is checked by route middleware.
 */
class UnansweredQuestionsRequest extends FormRequest
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
            'filter' => ['nullable', Rule::enum(UnansweredFilter::class)],
            'include_revised' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function filter(): UnansweredFilter
    {
        return $this->enum('filter', UnansweredFilter::class) ?? UnansweredFilter::All;
    }

    public function includeRevised(): bool
    {
        return $this->boolean('include_revised');
    }

    public function page(): int
    {
        return $this->integer('page', 1);
    }
}
