<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat\Calendar;

use Carbon\CarbonImmutable;
use Domain\Booking\Data\BookingRequestData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Booking a time from a shown calendar; shared by the chat and widget
 * controllers, which check the binding. Whether the time is still free is
 * the scheduler's call when it books.
 */
class BookMeetingRequest extends FormRequest
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
            'start_time' => ['required', 'date', 'after:now'],
            'name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email', 'max:254'],
            'timezone' => ['required', 'timezone:all'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'assistant_message_id' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function toData(): BookingRequestData
    {
        $validated = $this->validated();

        return new BookingRequestData(
            startTime: CarbonImmutable::parse((string) $validated['start_time'])->utc(),
            name: (string) $validated['name'],
            email: (string) $validated['email'],
            timezone: (string) $validated['timezone'],
            notes: filled($validated['notes'] ?? null) ? (string) $validated['notes'] : null,
        );
    }

    public function assistantMessageId(): ?string
    {
        return $this->input('assistant_message_id');
    }
}
