<?php

namespace App\Http\Controllers\Tickets;

use App\Http\Requests\Tickets\ReplyToTicketRequest;
use Domain\Helpdesk\Models\Ticket;
use Illuminate\Http\RedirectResponse;

/**
 * Append a reply to an existing support ticket.
 */
class ReplyToTicketController
{
    public function __invoke(ReplyToTicketRequest $request, Ticket $ticket): RedirectResponse
    {
        $data = $request->toData($ticket);

        $data->ticket->replies()->create([
            'user_id' => $data->user->id,
            'type' => 'user',
            'message' => $data->message,
        ]);

        return back()->with('success', 'Reply added successfully');
    }
}
