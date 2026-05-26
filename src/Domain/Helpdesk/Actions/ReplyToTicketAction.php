<?php

namespace Domain\Helpdesk\Actions;

use Domain\Helpdesk\Data\ReplyToTicketData;

class ReplyToTicketAction
{
    public function execute(ReplyToTicketData $data): void
    {
        $data->ticket->replies()->create([
            'user_id' => $data->user->id,
            'type' => 'user',
            'message' => $data->message,
        ]);
    }
}
