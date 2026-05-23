<?php

namespace App\Http\Controllers\Tickets;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TicketsController
{
    public function index(Request $request): Collection
    {
        return $request->user()
            ->tickets()
            ->with('replies')
            ->latest()
            ->get();
    }

    public function show(Request $request, Ticket $ticket): Response
    {
        $ticket->load('replies');

        return Inertia::render('TicketShow', [
            'ticket' => $ticket,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'title' => ['required', 'string'],
            'message' => ['required', 'string'],
            'priority' => ['required', 'string', 'in:low,medium,high,urgent'],
        ]);

        $request->user()->tickets()->create([
            'assigned_to' => 1,
            'title' => $request->input('title'),
            'message' => $request->input('message'),
            'priority' => $request->input('priority'),
        ]);

        return to_route('support')->with('success', 'Ticket created successfully.');
    }

    public function reply(Request $request, Ticket $ticket): RedirectResponse
    {
        $request->validate([
            'message' => ['required', 'string'],
        ]);

        $ticket->replies()->create([
            'user_id' => $request->user()->id,
            'type' => 'user',
            'message' => $request->input('message'),
        ]);

        return back()->with('success', 'Reply added successfully');
    }

    public function changePriority(Request $request, Ticket $ticket): RedirectResponse
    {
        $request->validate([
            'priority' => ['required', 'string', 'in:low,medium,high,urgent'],
        ]);

        $ticket->update(['priority' => $request->input('priority')]);

        return back()->with('success', 'Ticket priority updated successfully.');
    }
}
