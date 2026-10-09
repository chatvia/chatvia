<?php

declare(strict_types=1);

namespace Domain\Helpdesk\Data;

use Domain\Helpdesk\Models\Ticket;
use Spatie\LaravelData\Data;

/**
 * Input for changing a ticket's priority.
 */
class ChangeTicketPriorityData extends Data
{
    public function __construct(
        public Ticket $ticket,
        public string $priority,
    ) {}
}
