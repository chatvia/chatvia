<?php

namespace Domain\Conversation\Actions\Handoff;

use Domain\Billing\Enums\Capability;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Events\HandedOff;
use Domain\Conversation\Exceptions\HandoffUnavailableException;
use Domain\Conversation\Models\Conversation;

/**
 * Puts a visitor's conversation in the queue for a human operator. It only
 * ever moves a conversation into `pending`; one that is already waiting or
 * already with a human is returned as it is, without a second broadcast.
 */
class RequestHandoffAction
{
    /**
     * @throws HandoffUnavailableException when the plan or the agent's live-chat toggle does not allow handoff
     */
    public function execute(Conversation $conversation): HandoffStatus
    {
        $this->ensureHandoffIsAvailable($conversation);

        if (in_array($conversation->handoff_status, [HandoffStatus::Human, HandoffStatus::Pending], true)) {
            return $conversation->handoff_status;
        }

        $conversation->update(['handoff_status' => HandoffStatus::Pending]);

        HandedOff::dispatch($conversation->external_id, HandoffStatus::Pending->value);

        return HandoffStatus::Pending;
    }

    /**
     * The visitor endpoint has no signed-in user, so the plan gate cannot live
     * in route middleware: it is checked here against the plan of the team
     * that owns the agent. Live chat is widget-only, so the agent's widget
     * toggle can also switch it off on a capable plan.
     */
    private function ensureHandoffIsAvailable(Conversation $conversation): void
    {
        $agent = $conversation->agent;

        if ($agent === null || ! ($agent->team?->hasCapability(Capability::Handoff) ?? false)) {
            throw HandoffUnavailableException::notOnPlan();
        }

        $widgetSettings = $agent->settings('widget');

        if ($widgetSettings !== null && ! ($widgetSettings['handoff_enabled'] ?? true)) {
            throw HandoffUnavailableException::liveChatDisabled();
        }
    }
}
