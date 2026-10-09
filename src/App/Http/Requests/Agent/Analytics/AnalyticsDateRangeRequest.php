<?php

namespace App\Http\Requests\Agent\Analytics;

use Domain\Analytics\Data\DateRangeData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The optional `start_date` / `end_date` filter of the agent analytics data
 * endpoints. Missing dates fall back to the default window (the last 7 days).
 * Access to the agent and the analytics page is checked by the route
 * middleware.
 */
class AnalyticsDateRangeRequest extends FormRequest
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
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
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
