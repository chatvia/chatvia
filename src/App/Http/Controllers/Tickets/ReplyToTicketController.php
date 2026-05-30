<?php

namespace App\Http\Controllers\Tickets;

use App\Helpdesk\Models\Ticket;
use App\Http\Requests\Tickets\ReplyToTicketRequest;
use Domain\Helpdesk\Actions\ReplyToTicketAction;
use Illuminate\Http\RedirectResponse;

/**
 * Append a reply to an existing support ticket.
 */
class ReplyToTicketController
{
    public function __invoke(ReplyToTicketRequest $request, Ticket $ticket, ReplyToTicketAction $reply): RedirectResponse
    {
        $reply->execute($request->toData($ticket));

        return back()->with('success', 'Reply added successfully');
    }
}
