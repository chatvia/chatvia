<?php

namespace Domain\Helpdesk\Actions;

use App\Helpdesk\Models\Ticket;
use Domain\Helpdesk\Data\CreateTicketData;

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
