<?php

use Domain\Channel\Telegram\Actions\ReceiveTelegramUpdateAction;
use Domain\Channel\Telegram\Data\TelegramSenderData;
use Domain\Channel\Telegram\Data\TelegramUpdateData;
use Domain\Channel\Telegram\Jobs\HandleTelegramUpdateJob;
use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Illuminate\Support\Facades\Bus;

/**
 * TelegramWebhookTest covers routing, text-less updates and a repeated
 * delivery through the webhook. This covers what one bot cannot show: every
 * bot numbers its updates from its own counter, so the memory of queued
 * updates is kept per bot. It also checks that the job carries the sender,
 * which names the conversation and fills `{name}` in a command reply.
 */
it('remembers queued updates per bot and passes the sender on', function (): void {
    Bus::fake();
    $first = AgentTelegramBot::factory()->create();
    $second = AgentTelegramBot::factory()->create();
    $update = new TelegramUpdateData('1', '555', 'hello', new TelegramSenderData('Sam', 'sam_t'));
    $action = app(ReceiveTelegramUpdateAction::class);

    $action->execute($first, $update);
    $action->execute($second, $update);
    $action->execute($first, $update);
    $action->execute($second, $update);

    Bus::assertDispatchedTimes(HandleTelegramUpdateJob::class, 2);
    Bus::assertDispatched(HandleTelegramUpdateJob::class, fn (HandleTelegramUpdateJob $job): bool => $job->bot->is($first)
        && $job->chatId === '555'
        && $job->text === 'hello'
        && $job->from === ['first_name' => 'Sam', 'username' => 'sam_t']);
    Bus::assertDispatched(HandleTelegramUpdateJob::class, fn (HandleTelegramUpdateJob $job): bool => $job->bot->is($second));
});
