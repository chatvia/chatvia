<?php

namespace App\Http\Controllers\Tickets;

use App\Http\Requests\Tickets\ChangeTicketPriorityRequest;
use Domain\Helpdesk\Models\Ticket;
use Illuminate\Http\RedirectResponse;

/**
 * Update the priority on an existing support ticket.
 */
class ChangeTicketPriorityController
{
    public function __invoke(ChangeTicketPriorityRequest $request, Ticket $ticket): RedirectResponse
    {
        $ticket->update(['priority' => $request->toData($ticket)->priority]);

        return back()->with('success', 'Ticket priority updated successfully.');
    }
}
