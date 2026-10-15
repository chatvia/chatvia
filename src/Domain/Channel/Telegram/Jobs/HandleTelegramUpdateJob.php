<?php

declare(strict_types=1);

namespace Domain\Channel\Telegram\Jobs;

use Domain\Channel\Telegram\Actions\HandleTelegramUpdateAction;
use Domain\Channel\Telegram\Data\TelegramSenderData;
use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Domain\Channel\Telegram\Services\TelegramAdapter;
use Domain\Channel\Telegram\Services\TelegramChatResolver;
use Domain\Channel\Telegram\Services\TelegramClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;

/**
 * Processes one inbound Telegram message off the webhook (so the webhook returns
 * 200 immediately while the agent — a real LLM turn — runs in the background).
 * Holds no logic; defers to {@see HandleTelegramUpdateAction}.
 */
// The chat queue: a reply never waits behind e-mails or embeddings.
#[Queue('messages')]
// An agent turn bills a message, so it must never be retried.
#[Tries(1)]
// A reply may call the model and the tools several times.
#[Timeout(300)]
// A bot disconnected before the job runs has no one to reply to.
#[DeleteWhenMissingModels]
class HandleTelegramUpdateJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{first_name?: string|null, username?: string|null}  $from
     */
    public function __construct(
        public readonly AgentTelegramBot $bot,
        public readonly string $chatId,
        public readonly string $text,
        public readonly array $from,
    ) {}

    public function handle(HandleTelegramUpdateAction $action, TelegramClient $client, TelegramChatResolver $resolver): void
    {
        $action->execute(
            new TelegramAdapter($client, $resolver, $this->bot, $this->chatId, TelegramSenderData::fromArray($this->from)),
            $this->text,
        );
    }
}
