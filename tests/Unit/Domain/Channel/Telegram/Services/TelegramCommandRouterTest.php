<?php

use Domain\Channel\Telegram\Data\TelegramSenderData;
use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Domain\Channel\Telegram\Models\TelegramChat;
use Domain\Channel\Telegram\Services\TelegramChatResolver;
use Domain\Channel\Telegram\Services\TelegramCommandRouter;
use Tests\Support\Channel\Telegram\FakeTelegramClient;

/**
 * The command router resolves a slash command to an outcome before the
 * pipeline: a canned message (no LLM, not billed) or a prompt to run through
 * the agent. Plain messages and unknown commands fall through to the
 * pipeline. Built-in defaults (/start, /reset, /help) are merged with the
 * agent's stored edits, and /reset starts a fresh conversation.
 */
function telegramRouter(): TelegramCommandRouter
{
    return new TelegramCommandRouter(app(TelegramChatResolver::class), new FakeTelegramClient);
}

it('resolves each kind of message to its outcome', function (): void {
    $bot = AgentTelegramBot::factory()->create(['commands' => [
        ['command' => 'start', 'description' => 'Start', 'type' => 'message', 'value' => 'Hi {name}!'],
        ['command' => 'pricing', 'description' => 'Pricing', 'type' => 'prompt', 'value' => 'Explain our pricing.'],
        ['command' => 'hours', 'description' => 'Hours', 'type' => 'message', 'value' => 'We are open 9–5.'],
    ]]);
    $router = telegramRouter();

    $start = $router->handle($bot, new TelegramChat, '/start', new TelegramSenderData('Sam'));
    $prompt = $router->handle($bot, new TelegramChat, '/pricing@demo_bot now please');
    $message = $router->handle($bot, new TelegramChat, '/hours');

    expect($router->handle($bot, new TelegramChat, 'how do I reset my password?'))->toBeNull()
        ->and($router->handle($bot, new TelegramChat, '/totally_unknown'))->toBeNull()
        ->and($start->runThroughAgent)->toBeFalse()
        ->and($start->text)->toBe('Hi Sam!')
        ->and($prompt->runThroughAgent)->toBeTrue()
        ->and($prompt->text)->toBe('Explain our pricing.')
        ->and($message->runThroughAgent)->toBeFalse()
        ->and($message->text)->toBe('We are open 9–5.');
});

it('rotates to a fresh conversation on /reset, keeping the session', function (): void {
    $bot = AgentTelegramBot::factory()->create(['commands' => []]);
    $chat = app(TelegramChatResolver::class)->resolve($bot, '4242', new TelegramSenderData('Ann'));
    $originalConversationId = $chat->chat_conversation_id;
    $sessionId = $chat->chat_session_id;

    $outcome = telegramRouter()->handle($bot, $chat, '/reset');

    $chat->refresh();
    expect($outcome->runThroughAgent)->toBeFalse()
        ->and($chat->chat_session_id)->toBe($sessionId)
        ->and($chat->chat_conversation_id)->not->toBe($originalConversationId);
});
