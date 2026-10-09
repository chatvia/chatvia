<?php

namespace App\Http\Requests\Tickets;

use Domain\Helpdesk\Data\ChangeTicketPriorityData;
use Domain\Helpdesk\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a priority change on a support ticket (low, medium, high or
 * urgent). Only a user the ticket policy lets update the ticket may change it.
 */
class ChangeTicketPriorityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->route('ticket'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'priority' => ['required', 'string', 'in:low,medium,high,urgent'],
        ];
    }

    public function toData(Ticket $ticket): ChangeTicketPriorityData
    {
        return new ChangeTicketPriorityData(
            ticket: $ticket,
            priority: $this->string('priority')->toString(),
        );
    }
}
