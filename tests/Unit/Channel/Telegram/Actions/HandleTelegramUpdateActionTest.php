<?php

use Domain\Agent\Models\Agent;
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
use Illuminate\Support\Str;
use Tests\Support\Channel\StubChannelReplyRunner;
use Tests\Support\Telegram\FakeTelegramClient;

/**
 * The handle Action wires the inbound message end to end: resolve the chat, run
 * the agent for a reply, send it back, and stamp the chat. The pipeline (real
 * LLM) is stubbed here — this asserts the wiring, not the model's answer.
 */
function telegramHandleBot(): AgentTelegramBot
{
    $agent = Agent::factory()->create(['is_public' => true]);

    return AgentTelegramBot::create([
        'agent_id' => $agent->id,
        'bot_token' => '123:secret-token',
        'bot_username' => 'demo_bot',
        'webhook_secret' => Str::random(48),
        'is_enabled' => true,
    ]);
}

/**
 * Run one Telegram message through the handle action, wired to the stubbed
 * pipeline (a null reply echoes the message it was asked to run).
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

    // A returning chat, created earlier, so the stamp of this turn shows.
    $this->travelTo(Carbon::parse('2026-10-01 10:00:00'));
    app(TelegramChatResolver::class)->resolve($bot, '555', new TelegramSenderData('Sam'));
    $this->travelTo(Carbon::parse('2026-10-01 10:05:00'));

    telegramHandle($client, $runner, $bot, '555', 'hi there', 'Sam');

    expect($client->sent)->toHaveCount(1)
        ->and($client->sent[0]['token'])->toBe('123:secret-token')
        ->and($client->sent[0]['chatId'])->toBe('555')
        ->and($client->sent[0]['text'])->toBe('Hello from the agent');

    // A "typing…" indicator fires the moment the message lands.
    expect($client->actions)->not->toBeEmpty()
        ->and($client->actions[0]['action'])->toBe('typing')
        ->and($client->actions[0]['chatId'])->toBe('555');

    $chat = TelegramChat::query()->firstOrFail();
    expect($chat->chat_id)->toBe('555')
        ->and($chat->last_message_at->toDateTimeString())->toBe('2026-10-01 10:05:00');
});

it('answers neither messages nor commands for a deactivated workspace, and creates no chat', function (string $text): void {
    $bot = telegramHandleBot();
    deactivateWorkspaceOf($bot->agent);
    $client = new FakeTelegramClient;
    $runner = new StubChannelReplyRunner('should never be sent');

    telegramHandle($client, $runner, $bot, '555', $text, 'Sam');

    // Gated before the pipeline and the commands: one "unavailable" notice.
    expect($client->sent)->toHaveCount(1)
        ->and($client->sent[0]['text'])->toBe('Sorry — this assistant is unavailable right now. Please try again later.')
        ->and($runner->received())->toBeNull()
        ->and(TelegramChat::query()->count())->toBe(0);
})->with(['message' => 'hello', '/start command' => '/start']);

it('answers /start with the default message and never runs the pipeline', function (): void {
    $bot = telegramHandleBot();
    $client = new FakeTelegramClient;

    $runner = new StubChannelReplyRunner('PIPELINE REPLY');

    telegramHandle($client, $runner, $bot, '777', '/start', 'Sam');

    // Built-in /start defaults to "Hello" — a canned message, not the pipeline.
    expect($client->sent)->toHaveCount(1)
        ->and($client->sent[0]['text'])->toBe('Hello')
        ->and($client->sent[0]['text'])->not->toBe('PIPELINE REPLY');

    // Message-mode commands answer instantly — no "typing…", no LLM, nothing counted.
    expect($client->actions)->toBeEmpty();
});

it('runs a prompt-mode command through the agent pipeline', function (): void {
    $bot = telegramHandleBot();
    $bot->update(['commands' => [
        ['command' => 'pricing', 'description' => 'Pricing', 'type' => 'prompt', 'value' => 'What are our prices?'],
    ]]);
    $client = new FakeTelegramClient;

    $runner = new StubChannelReplyRunner;

    telegramHandle($client, $runner, $bot, '888', '/pricing', 'Sam');

    // The configured prompt — not the literal "/pricing" — is what the agent runs.
    expect($client->sent)->toHaveCount(1)
        ->and($client->sent[0]['text'])->toBe('What are our prices?');

    // Typing fired → the pipeline ran (prompt mode is billed like a normal turn).
    expect($client->actions)->not->toBeEmpty();
});
