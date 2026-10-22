<?php

namespace Domain\Channel\Telegram\Actions;

use Domain\Channel\Services\InboundDeliveryLog;
use Domain\Channel\Telegram\Data\TelegramUpdateData;
use Domain\Channel\Telegram\Jobs\HandleTelegramUpdateJob;
use Domain\Channel\Telegram\Models\AgentTelegramBot;

/**
 * Queues the text message of one verified Telegram update for a bot. Each
 * update is queued at most once — Telegram keeps re-delivering an
 * unacknowledged update for up to 24 hours, so the 1-day memory covers it.
 * Updates without a text message (edits, joins, media) are ignored.
 */
class ReceiveTelegramUpdateAction
{
    public function __construct(
        private readonly InboundDeliveryLog $deliveries,
    ) {}

    public function execute(AgentTelegramBot $bot, TelegramUpdateData $update): void
    {
        if (blank($update->text) || $update->chatId === null) {
            return;
        }

        $this->deliveries->once(
            $update->updateId !== null ? "telegram:update:{$bot->id}:{$update->updateId}" : null,
            now()->addDay(),
            function () use ($bot, $update): void {
                HandleTelegramUpdateJob::dispatch($bot, $update->chatId, $update->text, $update->sender->toArray());
            },
        );
    }
}
