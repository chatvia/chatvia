<?php

namespace App\Http\Requests\Tickets;

use App\Auth\Models\User;
use App\Helpdesk\Models\Ticket;
use Domain\Helpdesk\Data\ReplyToTicketData;
use Illuminate\Foundation\Http\FormRequest;

class ReplyToTicketRequest extends FormRequest
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
