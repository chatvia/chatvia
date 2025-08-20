<?php

namespace App\Http\Controllers\Tickets;

use App\Models\Ticket;
use Illuminate\Http\Request;

class TicketReplyController
{
    public function __invoke(Request $request, Ticket $ticket)
    {
        $user = $request->user();

        $request->validate([
            'message' => ['required', 'string'],
        ]);

        $ticket->replies()->create([
            'user_id' => $user->id,
            'type' => 'user',
            'message' => $request->input('message'),
        ]);

        return back()->with('success', 'Reply added successfully');
    }
}
