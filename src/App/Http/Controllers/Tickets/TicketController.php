<?php

namespace App\Http\Controllers\Tickets;

use App\Http\Requests\Tickets\CreateTicketRequest;
use Domain\Helpdesk\Actions\CreateTicketAction;
use Domain\Helpdesk\Models\Ticket;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Support tickets owned by the current user. Mutations beyond create — replies
 * and priority changes — live in dedicated invokable controllers in this
 * directory ({@see ReplyToTicketController}, {@see ChangeTicketPriorityController}).
 */
class TicketController
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
        // {ticket} binds globally by id — authorize ownership (TicketPolicy::view).
        Gate::authorize('view', $ticket);

        $ticket->load('replies');

        return Inertia::render('TicketShow', [
            'ticket' => $ticket,
        ]);
    }

    public function store(CreateTicketRequest $request, CreateTicketAction $createTicket): RedirectResponse
    {
        $createTicket->execute($request->toData());

        return to_route('support')->with('success', __('Ticket created successfully.'));
    }
}
