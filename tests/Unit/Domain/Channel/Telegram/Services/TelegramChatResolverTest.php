<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Telegram\Data\TelegramSenderData;
use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Domain\Channel\Telegram\Models\TelegramChat;
use Domain\Channel\Telegram\Services\TelegramChatResolver;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Illuminate\Support\Str;

/**
 * The resolver maps a Telegram (bot, chat_id) pair onto a persistent Chatvia
 * Session + Conversation, so a user keeps one ongoing conversation across
 * messages. First contact creates them; later messages reuse the same mapping.
 */
function telegramResolverBot(): AgentTelegramBot
{
    $agent = Agent::factory()->create();

    return AgentTelegramBot::create([
        'agent_id' => $agent->id,
        'bot_token' => '123:token',
        'bot_username' => 'demo_bot',
        'webhook_secret' => Str::random(48),
        'is_enabled' => true,
    ]);
}

it('creates a Session + Conversation + mapping on first contact', function (): void {
    $bot = telegramResolverBot();

    $chat = app(TelegramChatResolver::class)->resolve($bot, 555, new TelegramSenderData('Sam', 'sam_t'));

    expect($chat->agent_telegram_bot_id)->toBe($bot->id)
        ->and($chat->chat_id)->toBe('555')
        ->and($chat->first_name)->toBe('Sam')
        ->and($chat->username)->toBe('sam_t')
        ->and($chat->chat_session_id)->not->toBeNull()
        ->and($chat->chat_conversation_id)->not->toBeNull();

    // The Session is real and belongs to the agent, with no chatter user.
    $session = $chat->session;
    expect($session->agent_id)->toBe($bot->agent_id)
        ->and($session->user_id)->toBeNull()
        ->and($session->name)->toBe('Sam');
});

it('reuses the same mapping for a returning chat', function (): void {
    $bot = telegramResolverBot();
    $resolver = app(TelegramChatResolver::class);

    $first = $resolver->resolve($bot, 555, new TelegramSenderData('Sam'));
    $second = $resolver->resolve($bot, 555, new TelegramSenderData('Sam'));

    expect($second->id)->toBe($first->id);
    expect(TelegramChat::query()->count())->toBe(1);
});

it('keeps separate conversations for different chats', function (): void {
    $bot = telegramResolverBot();
    $resolver = app(TelegramChatResolver::class);

    $a = $resolver->resolve($bot, 100);
    $b = $resolver->resolve($bot, 200);

    expect($a->id)->not->toBe($b->id)
        ->and($a->chat_conversation_id)->not->toBe($b->chat_conversation_id);
    expect(TelegramChat::query()->count())->toBe(2);
});

it('tags the conversation as a Telegram conversation', function (): void {
    $bot = telegramResolverBot();

    $chat = app(TelegramChatResolver::class)->resolve($bot, 777, new TelegramSenderData('Lee'));

    expect(Conversation::query()
        ->where('external_id', $chat->conversation->external_id)
        ->where('source', ConversationSource::Telegram)
        ->exists())->toBeTrue();
});
