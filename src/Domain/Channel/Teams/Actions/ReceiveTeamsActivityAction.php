<?php

namespace Domain\Channel\Teams\Actions;

use Domain\Channel\Services\InboundDeliveryLog;
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

    /**
     * @param  array<string, mixed>  $activity  the activity as Microsoft delivered it; the queued job carries it whole
     */
    public function execute(AgentTeamsBot $bot, array $activity): void
    {
        if (! $this->shouldAnswer($activity)) {
            return;
        }

        $activityId = $activity['id'] ?? null;

        if (is_string($activityId)
            && ! $this->deliveries->firstDelivery("msteams:activity:{$bot->id}:{$activityId}", now()->addDay())) {
            return;
        }

        HandleTeamsActivityJob::dispatch($bot, $activity);
    }

    /**
     * @param  array<string, mixed>  $activity
     */
    private function shouldAnswer(array $activity): bool
    {
        $text = $activity['text'] ?? null;

        if (($activity['type'] ?? null) !== 'message' || ! is_string($text) || blank($text)) {
            return false;
        }

        if (! is_string($activity['conversation']['id'] ?? null) || ! is_string($activity['serviceUrl'] ?? null)) {
            return false;
        }

        $from = $activity['from'] ?? [];

        return ($from['role'] ?? null) !== 'bot'
            && ($from['id'] ?? null) !== ($activity['recipient']['id'] ?? null);
    }
}
