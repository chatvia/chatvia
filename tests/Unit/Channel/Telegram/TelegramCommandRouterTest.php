<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Domain\Channel\Telegram\Models\TelegramChat;
use Domain\Channel\Telegram\Services\TelegramChatResolver;
use Domain\Channel\Telegram\Services\TelegramCommandRouter;
use Illuminate\Support\Str;
use Tests\Support\Telegram\FakeTelegramClient;

/**
 * The command router answers slash commands before the chat pipeline: built-ins
 * (/start, /reset, /help) are code-defined, custom commands are canned replies,
 * and anything else falls through (null) to the real agent.
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
        'custom_commands' => $commands,
    ]);
}

it('lets a plain message fall through to the pipeline', function (): void {
    $reply = commandRouter()->handle(new AgentTelegramBot, new TelegramChat, 'how do I reset my password?');

    expect($reply)->toBeNull();
});

it('lets an unknown /command fall through to the pipeline', function (): void {
    $bot = botWithCommands([['command' => 'pricing', 'description' => 'Plans', 'reply' => '€99/mo']]);

    expect(commandRouter()->handle($bot, new TelegramChat, '/totally_unknown'))->toBeNull();
});

it('answers /start with the agent welcome message and the sender name', function (): void {
    $bot = botWithCommands();
    $bot->agent->setSetting('welcome_message', 'Ask me anything!', 'appearance');

    $reply = commandRouter()->handle($bot, new TelegramChat, '/start', ['first_name' => 'Sam']);

    expect($reply)->toContain('Ask me anything!')->toContain('Sam');
});

it('returns a custom command reply, stripping any @botname and args', function (): void {
    $bot = botWithCommands([['command' => 'pricing', 'description' => 'See our plans', 'reply' => 'Plans start at €99/mo.']]);

    expect(commandRouter()->handle($bot, new TelegramChat, '/pricing@demo_bot now please'))
        ->toBe('Plans start at €99/mo.');
});

it('lists built-ins and custom commands in /help', function (): void {
    $bot = botWithCommands([['command' => 'pricing', 'description' => 'See our plans', 'reply' => '€99/mo']]);

    $reply = commandRouter()->handle($bot, new TelegramChat, '/help');

    expect($reply)->toContain('/reset')->toContain('/help')->toContain('/pricing — See our plans');
});

it('builds a menu of built-ins followed by custom commands', function (): void {
    $bot = botWithCommands([['command' => 'pricing', 'description' => 'See our plans', 'reply' => '€99/mo']]);

    $menu = commandRouter()->menu($bot);

    expect(array_column($menu, 'command'))->toBe(['start', 'reset', 'help', 'pricing']);
});

it('rotates to a fresh conversation on /reset, keeping the session', function (): void {
    $bot = botWithCommands();
    $resolver = app(TelegramChatResolver::class);
    $chat = $resolver->resolve($bot, '4242', ['first_name' => 'Ann']);

    $originalConversationId = $chat->chat_conversation_id;
    $sessionId = $chat->chat_session_id;

    $reply = commandRouter()->handle($bot, $chat, '/reset');

    $chat->refresh();
    expect($reply)->toContain('new conversation')
        ->and($chat->chat_session_id)->toBe($sessionId)
        ->and($chat->chat_conversation_id)->not->toBe($originalConversationId);
});
