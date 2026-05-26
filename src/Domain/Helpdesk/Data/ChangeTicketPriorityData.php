<?php

namespace Domain\Helpdesk\Data;

use App\Helpdesk\Models\Ticket;
use Spatie\LaravelData\Data;

class ChangeTicketPriorityData extends Data
{
    public function __construct(
        public Ticket $ticket,
        public string $priority,
    ) {}
}
