<?php

namespace Domain\Helpdesk\Data;

use App\Identity\Workspace\Models\User;
use Domain\Helpdesk\Models\Ticket;
use Spatie\LaravelData\Data;

class ReplyToTicketData extends Data
{
    public function __construct(
        public Ticket $ticket,
        public User $user,
        public string $message,
    ) {}
}
