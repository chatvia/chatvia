<?php

namespace Domain\Helpdesk\Actions;

use App\Identity\Workspace\Enums\UserType;
use App\Identity\Workspace\Models\User;
use Domain\Helpdesk\Data\CreateTicketData;
use Domain\Helpdesk\Models\Ticket;

/**
 * Opens a support ticket for the submitting user and assigns it: to the user
 * in `helpdesk.default_assignee_id` when that user exists, otherwise to the
 * first admin, otherwise to nobody (`tickets.assigned_to` is nullable).
 */
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

    private function resolveAssignee(): ?int
    {
        $configured = filter_var(config('helpdesk.default_assignee_id'), FILTER_VALIDATE_INT);

        // A configured id that no longer exists would break the foreign key.
        if (is_int($configured) && $configured > 0 && User::query()->whereKey($configured)->exists()) {
            return $configured;
        }

        $adminId = User::query()->where('type', UserType::Admin)->orderBy('id')->value('id');

        return $adminId === null ? null : (int) $adminId;
    }
}
