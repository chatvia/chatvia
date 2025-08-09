<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TicketShowController
{
    public function __invoke(Request $request, Ticket $ticket)
    {
        $ticket->load('replies');

        return Inertia::render('TicketShow', [
            'ticket' => $ticket,
        ]);
    }
}
