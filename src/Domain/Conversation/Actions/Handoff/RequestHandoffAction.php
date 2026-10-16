<?php

namespace Domain\Conversation\Actions\Handoff;

use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Events\HandedOff;
use Domain\Conversation\Events\HandoffRequested;
use Domain\Conversation\Exceptions\HandoffUnavailableException;
use Domain\Conversation\Models\Conversation;

/**
 * Puts a visitor's conversation in the queue for a human operator. It only
 * ever moves a conversation into `pending`; one that is already waiting or
 * already with a human is returned as it is, without a second broadcast or
 * a second staff notification.
 */
class RequestHandoffAction
{
    /**
     * @throws HandoffUnavailableException when the agent's live-chat toggle does not allow handoff
     */
    public function execute(Conversation $conversation): HandoffStatus
    {
        $this->ensureHandoffIsAvailable($conversation);

        if (in_array($conversation->handoff_status, [HandoffStatus::Human, HandoffStatus::Pending], true)) {
            return $conversation->handoff_status;
        }

        $conversation->update(['handoff_status' => HandoffStatus::Pending]);

        HandedOff::dispatch($conversation->external_id, HandoffStatus::Pending->value);
        HandoffRequested::dispatch($conversation);

        return HandoffStatus::Pending;
    }

    /**
     * Live chat is widget-only, so the agent's widget toggle can switch it off.
     */
    private function ensureHandoffIsAvailable(Conversation $conversation): void
    {
        $agent = $conversation->agent;
        $widgetSettings = $agent?->settings('widget');

        if ($agent === null || ($widgetSettings !== null && ! ($widgetSettings['handoff_enabled'] ?? true))) {
            throw HandoffUnavailableException::liveChatDisabled();
        }
    }
}
