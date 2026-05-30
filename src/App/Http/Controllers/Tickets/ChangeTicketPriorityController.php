<?php

namespace App\Http\Controllers\Tickets;

use App\Helpdesk\Models\Ticket;
use App\Http\Requests\Tickets\ChangeTicketPriorityRequest;
use Domain\Helpdesk\Actions\ChangeTicketPriorityAction;
use Illuminate\Http\RedirectResponse;

/**
 * Update the priority on an existing support ticket.
 */
class ChangeTicketPriorityController
{
    public function __invoke(
        ChangeTicketPriorityRequest $request,
        Ticket $ticket,
        ChangeTicketPriorityAction $changePriority,
    ): RedirectResponse {
        $changePriority->execute($request->toData($ticket));

        return back()->with('success', 'Ticket priority updated successfully.');
    }
}
