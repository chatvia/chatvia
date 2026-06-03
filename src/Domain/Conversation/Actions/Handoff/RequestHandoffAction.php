<?php

namespace Domain\Conversation\Actions\Handoff;

use Domain\Billing\Enums\CapabilityEnum;
use Domain\Conversation\Events\HandedOff;
use Domain\Conversation\Exceptions\HandoffOperationException;
use Domain\Conversation\Models\Conversation;

class RequestHandoffAction
{
    /**
     * Returns the new status string. If already 'human' or 'pending', returns
     * that without dispatching a duplicate event.
     */
    public function execute(Conversation $conversation): string
    {
        // Visitor-side endpoint has no auth context, so the capability gate
        // can't live in HTTP middleware — enforce here against the plan of the
        // team that owns the agent the visitor is talking to.
        if (! ($conversation->agent?->team?->hasCapability(CapabilityEnum::HANDOFF) ?? false)) {
            throw HandoffOperationException::forbidden('Handoff is not available on this plan.');
        }

        // Per-agent toggle (Setup → Widget → Live Chat). Live Chat is widget-only,
        // so a disabled toggle blocks visitor handoff even on a capable plan.
        $widgetSettings = $conversation->agent?->settings('widget');
        if ($widgetSettings !== null && ! ($widgetSettings['handoff_enabled'] ?? true)) {
            throw HandoffOperationException::forbidden('Live chat is turned off for this agent.');
        }

        if ($conversation->handoff_status === 'human') {
            return 'human';
        }

        if ($conversation->handoff_status === 'pending') {
            return 'pending';
        }

        $conversation->update(['handoff_status' => 'pending']);

        HandedOff::dispatch(
            $conversation->external_id,
            'pending',
            null,
            null,
        );

        return 'pending';
    }
}
