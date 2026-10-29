<?php

use Domain\Channel\Services\ChannelAccessGate;
use Domain\Channel\Services\Replies\TypingReplyStrategy;
use Domain\Channel\Telegram\Actions\HandleTelegramUpdateAction;
use Domain\Channel\Telegram\Data\TelegramSenderData;
use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Domain\Channel\Telegram\Models\TelegramChat;
use Domain\Channel\Telegram\Services\TelegramAdapter;
use Domain\Channel\Telegram\Services\TelegramChatResolver;
use Domain\Channel\Telegram\Services\TelegramCommandRouter;
use Illuminate\Support\Carbon;
use Tests\Support\Channel\StubChannelReplyRunner;
use Tests\Support\Channel\Telegram\FakeTelegramClient;

/**
 * The handle Action wires the inbound message end to end: resolve the chat,
 * show "typing…", run the agent for a reply, send it back, and stamp the
 * chat. A deactivated workspace is stopped before both the pipeline and the
 * commands. The pipeline (real LLM) is stubbed here: this asserts the wiring,
 * not the model's answer.
 */
function telegramHandleBot(): AgentTelegramBot
{
    return AgentTelegramBot::factory()->create(['bot_token' => '123:secret-token']);
}

/**
 * Run one Telegram message through the handle action, wired to the stubbed
 * pipeline.
 */
function telegramHandle(FakeTelegramClient $client, StubChannelReplyRunner $runner, AgentTelegramBot $bot, string $chatId, string $text, string $firstName = 'Sam'): void
{
    $resolver = app(TelegramChatResolver::class);

    $action = new HandleTelegramUpdateAction(
        app(ChannelAccessGate::class),
        new TypingReplyStrategy($runner),
        new TelegramCommandRouter($resolver, $client),
    );

    $action->execute(new TelegramAdapter($client, $resolver, $bot, $chatId, new TelegramSenderData($firstName)), $text);
}

it('resolves the chat, sends the agent reply, and stamps last_message_at', function (): void {
    $bot = telegramHandleBot();
    $client = new FakeTelegramClient;
    $runner = new StubChannelReplyRunner('Hello from the agent');
    $this->travelTo(Carbon::parse('2026-10-01 10:00:00'));
    app(TelegramChatResolver::class)->resolve($bot, '555', new TelegramSenderData('Sam'));
    $this->travelTo(Carbon::parse('2026-10-01 10:05:00'));

    telegramHandle($client, $runner, $bot, '555', 'hi there', 'Sam');

    $chat = TelegramChat::query()->firstOrFail();
    expect($client->sent)->toHaveCount(1)
        ->and($client->sent[0]['token'])->toBe('123:secret-token')
        ->and($client->sent[0]['chatId'])->toBe('555')
        ->and($client->sent[0]['text'])->toBe('Hello from the agent')
        ->and($client->actions[0]['action'])->toBe('typing')
        ->and($client->actions[0]['chatId'])->toBe('555')
        ->and($chat->chat_id)->toBe('555')
        ->and($chat->last_message_at->toDateTimeString())->toBe('2026-10-01 10:05:00');
});

it('answers neither messages nor commands for a deactivated workspace, and creates no chat', function (string $text): void {
    $bot = telegramHandleBot();
    deactivateWorkspaceOf($bot->agent);
    $client = new FakeTelegramClient;
    $runner = new StubChannelReplyRunner('should never be sent');

    telegramHandle($client, $runner, $bot, '555', $text, 'Sam');

    expect($client->sent)->toHaveCount(1)
        ->and($client->sent[0]['text'])->toBe('Sorry — this assistant is unavailable right now. Please try again later.')
        ->and($runner->received())->toBeNull()
        ->and(TelegramChat::query()->count())->toBe(0);
})->with(['message' => 'hello', '/start command' => '/start']);
