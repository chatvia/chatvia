<?php

namespace Domain\Channel\Slack\Actions;

use Domain\Channel\Services\InboundDeliveryLog;
use Domain\Channel\Slack\Data\SlackEventData;
use Domain\Channel\Slack\Jobs\HandleSlackEventJob;
use Domain\Channel\Slack\Models\AgentSlackApp;

/**
 * Queues the message of one verified Slack event callback for an app. Each
 * event is queued at most once — Slack retries a failed delivery at most 3
 * times within minutes, so the 1-day memory comfortably covers it.
 *
 * Only `app_mention`s (the bot @mentioned in a channel) and direct messages
 * are answered. Everything else — channel chatter that doesn't mention the
 * bot, message edits/joins (subtyped), and crucially any bot message including
 * our own replies echoing back — is ignored, or the bot would answer itself in
 * a loop.
 */
class ReceiveSlackEventAction
{
    public function __construct(
        private readonly InboundDeliveryLog $deliveries,
    ) {}

    public function execute(AgentSlackApp $app, SlackEventData $event): void
    {
        if ($event->eventId !== null
            && ! $this->deliveries->firstDelivery("slack:event:{$app->id}:{$event->eventId}", now()->addDay())) {
            return;
        }

        if (! $this->shouldAnswer($app, $event)) {
            return;
        }

        HandleSlackEventJob::dispatch($app, (string) $event->channelId, (string) $event->text, $event->threadTs);
    }

    private function shouldAnswer(AgentSlackApp $app, SlackEventData $event): bool
    {
        if ($event->text === null || trim($event->text) === '' || $event->channelId === null) {
            return false;
        }

        if ($event->fromBot || $event->hasSubtype || $event->userId === $app->bot_user_id) {
            return false;
        }

        return match ($event->type) {
            'app_mention' => true,
            'message' => $event->channelType === 'im',
            default => false,
        };
    }
}
