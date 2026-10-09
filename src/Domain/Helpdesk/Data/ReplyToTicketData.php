<?php

declare(strict_types=1);

namespace Domain\Helpdesk\Data;

use App\Identity\Workspace\Models\User;
use Domain\Helpdesk\Models\Ticket;
use Spatie\LaravelData\Data;

/**
 * Input for replying to a support ticket as its author.
 */
class ReplyToTicketData extends Data
{
    public function __construct(
        public Ticket $ticket,
        public User $user,
        public string $message,
    ) {}
}
