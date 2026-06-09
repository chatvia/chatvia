<?php

namespace Domain\Channel\Telegram\Jobs;

use Domain\Channel\Telegram\Actions\HandleTelegramUpdateAction;
use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Processes one inbound Telegram message off the webhook (so the webhook returns
 * 200 immediately while the agent — a real LLM turn — runs in the background).
 * Holds no logic; defers to {@see HandleTelegramUpdateAction}. `tries = 1`: an
 * agent turn bills a message, so it must never be retried.
 */
class HandleTelegramUpdateJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * @param  array{first_name?: string|null, username?: string|null}  $from
     */
    public function __construct(
        public readonly AgentTelegramBot $bot,
        public readonly string $chatId,
        public readonly string $text,
        public readonly array $from,
    ) {}

    public function handle(HandleTelegramUpdateAction $action): void
    {
        $action->execute($this->bot, $this->chatId, $this->text, $this->from);
    }
}
