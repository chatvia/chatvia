<?php

namespace App\Http\Controllers\Tickets;

use Illuminate\Http\Request;

class TicketStoreController
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'title' => ['required', 'string'],
            'message' => ['required', 'string'],
            'priority' => ['required', 'string', 'in:low,medium,high,urgent'],
        ]);

        $user->tickets()->create([
            'assigned_to' => 1,
            'title' => $request->input('title'),
            'message' => $request->input('message'),
            'priority' => $request->input('priority'),
        ]);

        return to_route('support')->with('success', 'Ticket created successfully.');
    }
}
