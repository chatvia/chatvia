<?php

namespace Domain\Helpdesk\Actions;

use Domain\Helpdesk\Data\ChangeTicketPriorityData;

class ChangeTicketPriorityAction
{
    public function execute(ChangeTicketPriorityData $data): void
    {
        $data->ticket->update(['priority' => $data->priority]);
    }
}
