<?php

namespace Domain\Chat\Actions;

use App\Billing\Enums\CapabilityEnum;
use Domain\Chat\Events\ChatHandoffEvent;
use Domain\Chat\Exceptions\HandoffOperationException;
use Domain\Chat\Models\ChatConversation;

class RequestHandoffAction
{
    /**
     * Returns the new status string. If already 'human' or 'pending', returns
     * that without dispatching a duplicate event.
     */
    public function execute(ChatConversation $conversation): string
    {
        // Visitor-side endpoint has no auth context, so the capability gate
        // can't live in HTTP middleware — enforce here on the workspace
        // owner of the agent the visitor is talking to.
        $owner = $conversation->agent?->user;
        if (! $owner?->hasCapability(CapabilityEnum::HANDOFF)) {
            throw HandoffOperationException::forbidden('Handoff is not available on this plan.');
        }

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
