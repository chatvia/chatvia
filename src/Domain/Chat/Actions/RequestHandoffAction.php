<?php

namespace Domain\Chat\Actions;

use Domain\Chat\Events\ChatHandoffEvent;
use Domain\Chat\Models\ChatConversation;

class RequestHandoffAction
{
    /**
     * Returns the new status string. If already 'human' or 'pending', returns
     * that without dispatching a duplicate event.
     */
    public function execute(ChatConversation $conversation): string
    {
        if ($conversation->handoff_status === 'human') {
            return 'human';
        }

        if ($conversation->handoff_status === 'pending') {
            return 'pending';
        }

        $conversation->update(['handoff_status' => 'pending']);

        ChatHandoffEvent::dispatch(
            $conversation->external_id,
            'pending',
            null,
            null,
        );

        return 'pending';
    }
}
