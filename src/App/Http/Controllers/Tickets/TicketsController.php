<?php

namespace App\Http\Controllers\Tickets;

use App\Helpdesk\Models\Ticket;
use App\Http\Requests\Tickets\ChangeTicketPriorityRequest;
use App\Http\Requests\Tickets\CreateTicketRequest;
use App\Http\Requests\Tickets\ReplyToTicketRequest;
use Domain\Helpdesk\Actions\ChangeTicketPriorityAction;
use Domain\Helpdesk\Actions\CreateTicketAction;
use Domain\Helpdesk\Actions\ReplyToTicketAction;
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

    public function show(Ticket $ticket): Response
    {
        $ticket->load('replies');

        return Inertia::render('TicketShow', [
            'ticket' => $ticket,
        ]);
    }

    public function store(CreateTicketRequest $request, CreateTicketAction $createTicket): RedirectResponse
    {
        $createTicket->execute($request->toData());

        return to_route('support')->with('success', 'Ticket created successfully.');
    }

    public function reply(ReplyToTicketRequest $request, Ticket $ticket, ReplyToTicketAction $reply): RedirectResponse
    {
        $reply->execute($request->toData($ticket));

        return back()->with('success', 'Reply added successfully');
    }

    public function changePriority(
        ChangeTicketPriorityRequest $request,
        Ticket $ticket,
        ChangeTicketPriorityAction $changePriority,
    ): RedirectResponse {
        $changePriority->execute($request->toData($ticket));

        return back()->with('success', 'Ticket priority updated successfully.');
    }
}
