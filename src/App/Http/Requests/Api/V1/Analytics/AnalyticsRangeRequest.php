<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Analytics;

use Dedoc\Scramble\Attributes\QueryParameter;
use Domain\Analytics\Data\DateRangeData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The optional `start_date` / `end_date` filter of the analytics endpoints.
 * Missing dates fall back to the default window (the last 7 days including
 * today).
 */
#[QueryParameter('start_date', description: 'First day of the range (`Y-m-d`). Defaults to 6 days ago.', type: 'string')]
#[QueryParameter('end_date', description: 'Last day of the range (`Y-m-d`). Defaults to today.', type: 'string')]
class AnalyticsRangeRequest extends FormRequest
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
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    public function toData(): DateRangeData
    {
        return DateRangeData::fromDates(
            $this->validated('start_date'),
            $this->validated('end_date'),
        );
    }
}
