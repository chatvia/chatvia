<?php

namespace Domain\Helpdesk\Data;

use App\Workspace\Models\User;
use App\Helpdesk\Models\Ticket;
use Spatie\LaravelData\Data;

class ReplyToTicketData extends Data
{
    public function __construct(
        public Ticket $ticket,
        public User $user,
        public string $message,
    ) {}
}
