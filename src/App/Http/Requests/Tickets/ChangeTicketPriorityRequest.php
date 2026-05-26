<?php

namespace App\Http\Requests\Tickets;

use App\Helpdesk\Models\Ticket;
use Domain\Helpdesk\Data\ChangeTicketPriorityData;
use Illuminate\Foundation\Http\FormRequest;

class ChangeTicketPriorityRequest extends FormRequest
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
