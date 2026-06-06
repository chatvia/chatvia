<?php

namespace Domain\Helpdesk\Actions;

use App\Identity\Workspace\Models\User;
use Domain\Helpdesk\Data\CreateTicketData;
use Domain\Helpdesk\Models\Ticket;
use Support\Enums\User\UserType;

class CreateTicketAction
{
    public function execute(CreateTicketData $data): Ticket
    {
        return $data->user->tickets()->create([
            'assigned_to' => $this->resolveAssignee(),
            'title' => $data->title,
            'message' => $data->message,
            'priority' => $data->priority,
        ]);
    }

    private function resolveAssignee(): int
    {
        $configured = config('helpdesk.default_assignee_id');
        if (is_int($configured) && $configured > 0) {
            return $configured;
        }

        return (int) User::query()->where('type', UserType::Admin)->value('id');
    }
}
