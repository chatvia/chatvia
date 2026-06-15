<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Domain\Channel\Telegram\Models\TelegramChat;
use Domain\Channel\Telegram\Services\TelegramChatResolver;
use Domain\Channel\Telegram\Services\TelegramCommandRouter;
use Illuminate\Support\Str;
use Tests\Support\Telegram\FakeTelegramClient;

/**
 * The command router resolves a slash command to an outcome before the pipeline:
 * a canned message (no LLM/billing) or a prompt to run through the agent. Built-in
 * defaults (/start, /reset, /help) are merged with the agent's stored edits.
 */
function commandRouter(): TelegramCommandRouter
{
    return new TelegramCommandRouter(app(TelegramChatResolver::class), new FakeTelegramClient);
}

function botWithCommands(array $commands = []): AgentTelegramBot
{
    $agent = Agent::factory()->create(['is_public' => true]);

    return AgentTelegramBot::create([
        'agent_id' => $agent->id,
        'bot_token' => '123:secret-token',
        'bot_username' => 'demo_bot',
        'webhook_secret' => Str::random(48),
        'enabled' => true,
        'commands' => $commands,
    ]);
}

it('lets a plain message fall through to the pipeline', function (): void {
    expect(commandRouter()->handle(new AgentTelegramBot, new TelegramChat, 'how do I reset my password?'))->toBeNull();
});

it('lets an unknown /command fall through to the pipeline', function (): void {
    $bot = botWithCommands([['command' => 'pricing', 'description' => 'Plans', 'type' => 'message', 'value' => '€99']]);

    expect(commandRouter()->handle($bot, new TelegramChat, '/totally_unknown'))->toBeNull();
});

it('answers /start with the default "Hello" message', function (): void {
    $outcome = commandRouter()->handle(botWithCommands(), new TelegramChat, '/start');

    expect($outcome->runThroughAgent)->toBeFalse()
        ->and($outcome->text)->toBe('Hello');
});

it('uses an edited /start message and interpolates {name}', function (): void {
    $bot = botWithCommands([['command' => 'start', 'description' => 'Start', 'type' => 'message', 'value' => 'Hi {name}!']]);

    $outcome = commandRouter()->handle($bot, new TelegramChat, '/start', ['first_name' => 'Sam']);

    expect($outcome->runThroughAgent)->toBeFalse()
        ->and($outcome->text)->toBe('Hi Sam!');
});

it('returns a prompt outcome for a prompt-mode command, stripping @botname and args', function (): void {
    $bot = botWithCommands([['command' => 'pricing', 'description' => 'Pricing', 'type' => 'prompt', 'value' => 'Explain our pricing.']]);

    $outcome = commandRouter()->handle($bot, new TelegramChat, '/pricing@demo_bot now please');

    expect($outcome->runThroughAgent)->toBeTrue()
        ->and($outcome->text)->toBe('Explain our pricing.');
});

it('returns a message outcome for a message-mode command', function (): void {
    $bot = botWithCommands([['command' => 'hours', 'description' => 'Hours', 'type' => 'message', 'value' => 'We are open 9–5.']]);

    $outcome = commandRouter()->handle($bot, new TelegramChat, '/hours');

    expect($outcome->runThroughAgent)->toBeFalse()
        ->and($outcome->text)->toBe('We are open 9–5.');
});

it('auto-lists the commands for a blank /help', function (): void {
    $bot = botWithCommands([['command' => 'pricing', 'description' => 'See our plans', 'type' => 'prompt', 'value' => 'Explain pricing']]);

    $outcome = commandRouter()->handle($bot, new TelegramChat, '/help');

    expect($outcome->runThroughAgent)->toBeFalse()
        ->and($outcome->text)->toContain('/pricing — See our plans')
        ->and($outcome->text)->toContain('/reset');
});

it('exposes built-ins (flagged) followed by custom commands for editing', function (): void {
    $bot = botWithCommands([['command' => 'pricing', 'description' => 'Plans', 'type' => 'prompt', 'value' => 'Explain pricing']]);

    $editing = commandRouter()->forEditing($bot);

    expect(array_column($editing, 'command'))->toBe(['start', 'reset', 'help', 'pricing']);
    expect($editing[0])->toMatchArray(['command' => 'start', 'type' => 'message', 'value' => 'Hello', 'builtin' => true]);
    expect($editing[3]['builtin'])->toBeFalse();
});

it('registers a "/" menu of built-ins followed by custom commands', function (): void {
    $bot = botWithCommands([['command' => 'pricing', 'description' => 'See our plans', 'type' => 'prompt', 'value' => 'Explain pricing']]);
    $client = new FakeTelegramClient;

    (new TelegramCommandRouter(app(TelegramChatResolver::class), $client))->sync($bot);

    expect($client->commands)->toHaveCount(1)
        ->and(array_column($client->commands[0]['commands'], 'command'))->toBe(['start', 'reset', 'help', 'pricing']);
});

it('rotates to a fresh conversation on /reset, keeping the session', function (): void {
    $bot = botWithCommands();
    $chat = app(TelegramChatResolver::class)->resolve($bot, '4242', ['first_name' => 'Ann']);

    $originalConversationId = $chat->chat_conversation_id;
    $sessionId = $chat->chat_session_id;

    $outcome = commandRouter()->handle($bot, $chat, '/reset');

    $chat->refresh();
    expect($outcome->runThroughAgent)->toBeFalse()
        ->and($outcome->text)->toContain('new conversation')
        ->and($chat->chat_session_id)->toBe($sessionId)
        ->and($chat->chat_conversation_id)->not->toBe($originalConversationId);
});
