<?php

namespace App\Http\Requests\Tickets;

use App\Identity\Workspace\Models\User;
use Domain\Helpdesk\Data\ReplyToTicketData;
use Domain\Helpdesk\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;

class ReplyToTicketRequest extends FormRequest
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
            'message' => ['required', 'string'],
        ];
    }

    public function toData(Ticket $ticket): ReplyToTicketData
    {
        /** @var User $user */
        $user = $this->user();

        return new ReplyToTicketData(
            ticket: $ticket,
            user: $user,
            message: $this->string('message')->toString(),
        );
    }
}
