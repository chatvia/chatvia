<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;

class TicketChangePriorityController
{
    public function __invoke(Request $request, Ticket $ticket)
    {
        $request->validate([
            'priority' => ['required', 'string', 'in:low,medium,high,urgent'],
        ]);

        $ticket->update(['priority' => $request->input('priority')]);

        return back()->with('success', 'Ticket priority updated successfully.');
    }
}
