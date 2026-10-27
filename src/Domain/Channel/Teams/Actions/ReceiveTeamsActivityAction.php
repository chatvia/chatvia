<?php

namespace Domain\Channel\Teams\Actions;

use Domain\Channel\Services\InboundDeliveryLog;
use Domain\Channel\Teams\Data\TeamsInboundActivityData;
use Domain\Channel\Teams\Jobs\HandleTeamsActivityJob;
use Domain\Channel\Teams\Models\AgentTeamsBot;

/**
 * Queues one verified Bot Framework activity for a Teams bot. Each activity is
 * queued at most once — the connector re-delivers a timed-out activity
 * shortly after (its delivery deadline is 15 s), so the 1-day memory
 * comfortably outlives the retry window.
 *
 * Only user message activities are answered. Everything else — conversation
 * updates, reactions, typing echoes, and crucially anything sent by a bot
 * (including our own replies, would they ever come back) — is ignored, or the
 * bot could answer itself in a loop. In channels Teams already delivers a
 * message only when the bot is @mentioned, so no mention filtering is needed.
 */
class ReceiveTeamsActivityAction
{
    public function __construct(
        private readonly InboundDeliveryLog $deliveries,
    ) {}

    public function execute(AgentTeamsBot $bot, TeamsInboundActivityData $activity): void
    {
        if (! $this->shouldAnswer($activity)) {
            return;
        }

        $this->deliveries->once(
            $activity->id !== null ? "msteams:activity:{$bot->id}:{$activity->id}" : null,
            now()->addDay(),
            function () use ($bot, $activity): void {
                HandleTeamsActivityJob::dispatch($bot, $activity->message);
            },
        );
    }

    private function shouldAnswer(TeamsInboundActivityData $activity): bool
    {
        if ($activity->type !== 'message' || ! $activity->hasText) {
            return false;
        }

        if (blank($activity->message->conversationId) || blank($activity->message->serviceUrl)) {
            return false;
        }

        return $activity->fromRole !== 'bot'
            && $activity->fromId !== $activity->recipientId;
    }
}
