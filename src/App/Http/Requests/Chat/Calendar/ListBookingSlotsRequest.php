<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat\Calendar;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The week of a shown calendar the visitor browses to. The range is clamped
 * by the action, so only its shape is checked here.
 */
class ListBookingSlotsRequest extends FormRequest
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
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after:from'],
        ];
    }

    public function from(): CarbonImmutable
    {
        return CarbonImmutable::parse((string) $this->validated('from'))->utc();
    }

    public function to(): CarbonImmutable
    {
        return CarbonImmutable::parse((string) $this->validated('to'))->utc();
    }
}
