<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Form;

use Dedoc\Scramble\Attributes\QueryParameter;
use Domain\Form\Data\SubmissionSelectionData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `GET /api/v1/agents/{agent}/forms/{form}/submissions/export` — which
 * submissions the CSV holds: the ones named by `ids`, or every submitted one
 * when `ids` is left out.
 */
#[QueryParameter('ids', description: 'Submission IDs to export. Leave it out to export every submitted entry.', type: 'string[]')]
class ExportApiSubmissionsRequest extends FormRequest
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
            'ids' => ['sometimes', 'array', 'max:1000'],
            'ids.*' => ['uuid'],
        ];
    }

    /**
     * The selection; empty means "all".
     */
    public function toData(): SubmissionSelectionData
    {
        return new SubmissionSelectionData(
            ids: array_values(array_map(strval(...), $this->validated('ids') ?? [])),
        );
    }
}
