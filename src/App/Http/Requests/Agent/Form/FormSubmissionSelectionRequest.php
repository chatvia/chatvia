<?php

declare(strict_types=1);

namespace App\Http\Requests\Agent\Form;

use Domain\Form\Data\SubmissionSelectionData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The submissions a bulk action on the Forms → Submissions page applies to,
 * by external id. An empty selection means "all" for the export and "none"
 * for a delete. Access is gated by the `agent.permission:agent.leads.*`
 * route middleware.
 */
class FormSubmissionSelectionRequest extends FormRequest
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
            'external_ids' => ['sometimes', 'array'],
        ];
    }

    /**
     * The selection; anything that is not a string is ignored.
     */
    public function toData(): SubmissionSelectionData
    {
        return new SubmissionSelectionData(
            ids: array_values(array_filter((array) $this->input('external_ids', []), is_string(...))),
        );
    }
}
