<?php

use Domain\Channel\Telegram\Data\TelegramSenderData;
use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Domain\Channel\Telegram\Models\TelegramChat;
use Domain\Channel\Telegram\Services\TelegramChatResolver;
use Domain\Channel\Telegram\Services\TelegramCommandRouter;
use Tests\Support\Channel\Telegram\FakeTelegramClient;

/**
 * The command router resolves a slash command to an outcome before the pipeline:
 * a canned message (no LLM/billing) or a prompt to run through the agent. Built-in
 * defaults (/start, /reset, /help) are merged with the agent's stored edits.
 */
function telegramRouter(): TelegramCommandRouter
{
    return new TelegramCommandRouter(app(TelegramChatResolver::class), new FakeTelegramClient);
}

/** @param  list<array<string, string>>  $commands */
function telegramRouterBot(array $commands = []): AgentTelegramBot
{
    return AgentTelegramBot::factory()->create(['commands' => $commands]);
}

it('lets a plain message fall through to the pipeline', function (): void {
    expect(telegramRouter()->handle(new AgentTelegramBot, new TelegramChat, 'how do I reset my password?'))->toBeNull();
});

it('lets an unknown /command fall through to the pipeline', function (): void {
    $bot = telegramRouterBot([['command' => 'pricing', 'description' => 'Plans', 'type' => 'message', 'value' => '€99']]);

    expect(telegramRouter()->handle($bot, new TelegramChat, '/totally_unknown'))->toBeNull();
});

it('answers /start with the default "Hello" message', function (): void {
    $outcome = telegramRouter()->handle(telegramRouterBot(), new TelegramChat, '/start');

    expect($outcome->runThroughAgent)->toBeFalse()
        ->and($outcome->text)->toBe('Hello');
});

it('uses an edited /start message and interpolates {name}', function (): void {
    $bot = telegramRouterBot([['command' => 'start', 'description' => 'Start', 'type' => 'message', 'value' => 'Hi {name}!']]);

    $outcome = telegramRouter()->handle($bot, new TelegramChat, '/start', new TelegramSenderData('Sam'));

    expect($outcome->runThroughAgent)->toBeFalse()
        ->and($outcome->text)->toBe('Hi Sam!');
});

it('returns a prompt outcome for a prompt-mode command, stripping @botname and args', function (): void {
    $bot = telegramRouterBot([['command' => 'pricing', 'description' => 'Pricing', 'type' => 'prompt', 'value' => 'Explain our pricing.']]);

    $outcome = telegramRouter()->handle($bot, new TelegramChat, '/pricing@demo_bot now please');

    expect($outcome->runThroughAgent)->toBeTrue()
        ->and($outcome->text)->toBe('Explain our pricing.');
});

it('returns a message outcome for a message-mode command', function (): void {
    $bot = telegramRouterBot([['command' => 'hours', 'description' => 'Hours', 'type' => 'message', 'value' => 'We are open 9–5.']]);

    $outcome = telegramRouter()->handle($bot, new TelegramChat, '/hours');

    expect($outcome->runThroughAgent)->toBeFalse()
        ->and($outcome->text)->toBe('We are open 9–5.');
});

it('auto-lists the commands for a blank /help', function (): void {
    $bot = telegramRouterBot([['command' => 'pricing', 'description' => 'See our plans', 'type' => 'prompt', 'value' => 'Explain pricing']]);

    $outcome = telegramRouter()->handle($bot, new TelegramChat, '/help');

    expect($outcome->runThroughAgent)->toBeFalse()
        ->and($outcome->text)->toContain('/pricing — See our plans')
        ->and($outcome->text)->toContain('/reset');
});

it('exposes built-ins (flagged) followed by custom commands for editing', function (): void {
    $bot = telegramRouterBot([['command' => 'pricing', 'description' => 'Plans', 'type' => 'prompt', 'value' => 'Explain pricing']]);

    $editing = telegramRouter()->forEditing($bot);

    expect(array_column($editing, 'command'))->toBe(['start', 'reset', 'help', 'pricing']);
    expect($editing[0])->toMatchArray(['command' => 'start', 'type' => 'message', 'value' => 'Hello', 'builtin' => true]);
    expect($editing[3]['builtin'])->toBeFalse();
});

it('registers a "/" menu of built-ins followed by custom commands', function (): void {
    $bot = telegramRouterBot([['command' => 'pricing', 'description' => 'See our plans', 'type' => 'prompt', 'value' => 'Explain pricing']]);
    $client = new FakeTelegramClient;

    new TelegramCommandRouter(app(TelegramChatResolver::class), $client)->sync($bot);

    expect($client->commands)->toHaveCount(1)
        ->and(array_column($client->commands[0]['commands'], 'command'))->toBe(['start', 'reset', 'help', 'pricing']);
});

it('rotates to a fresh conversation on /reset, keeping the session', function (): void {
    $bot = telegramRouterBot();
    $chat = app(TelegramChatResolver::class)->resolve($bot, '4242', new TelegramSenderData('Ann'));

    $originalConversationId = $chat->chat_conversation_id;
    $sessionId = $chat->chat_session_id;

    $outcome = telegramRouter()->handle($bot, $chat, '/reset');

    $chat->refresh();
    expect($outcome->runThroughAgent)->toBeFalse()
        ->and($outcome->text)->toContain('new conversation')
        ->and($chat->chat_session_id)->toBe($sessionId)
        ->and($chat->chat_conversation_id)->not->toBe($originalConversationId);
});

it('sends the built-in command texts in the language the owner pinned for the agent', function (): void {
    $bot = telegramRouterBot([['command' => 'greet', 'description' => 'Greet', 'type' => 'message', 'value' => 'Hallo {name}!']]);
    $bot->agent->setSetting(key: 'language', value: 'de', group: 'embed');
    $bot = $bot->fresh();

    expect(telegramRouter()->handle($bot, new TelegramChat, '/start')->text)->toBe('Hallo')
        ->and(telegramRouter()->handle($bot, new TelegramChat, '/help')->text)
        ->toStartWith('Das kann ich für Sie tun:')
        ->toContain('/start — Unterhaltung starten')
        ->and(telegramRouter()->handle($bot, new TelegramChat, '/greet')->text)->toBe('Hallo zusammen!');
});
