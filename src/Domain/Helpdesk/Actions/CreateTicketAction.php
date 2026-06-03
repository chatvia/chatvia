<?php

namespace Domain\Helpdesk\Actions;

use Domain\Helpdesk\Data\CreateTicketData;
use Domain\Helpdesk\Models\Ticket;

class CreateTicketAction
{
    public function execute(CreateTicketData $data): Ticket
    {
        return $data->user->tickets()->create([
            'assigned_to' => 1,
            'title' => $data->title,
            'message' => $data->message,
            'priority' => $data->priority,
        ]);
    }
}
