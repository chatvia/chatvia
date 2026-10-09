<?php

declare(strict_types=1);

namespace Domain\Helpdesk\Data;

use App\Identity\Workspace\Models\User;
use Spatie\LaravelData\Data;

/**
 * Input for opening a support ticket.
 */
class CreateTicketData extends Data
{
    public function __construct(
        public User $user,
        public string $title,
        public string $message,
        public string $priority,
    ) {}
}
