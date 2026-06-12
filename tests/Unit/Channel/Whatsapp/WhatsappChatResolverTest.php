<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Whatsapp\Models\AgentWhatsappNumber;
use Domain\Channel\Whatsapp\Models\WhatsappChat;
use Domain\Channel\Whatsapp\Services\WhatsappChatResolver;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Session;
use Illuminate\Support\Str;

/**
 * The resolver maps a WhatsApp (number, wa_id) pair onto a persistent
 * Chatvia Session + Conversation, so a WhatsApp user keeps one ongoing
 * conversation across messages. First contact creates them (the profile name
 * arrives free in the webhook payload); later messages reuse the mapping.
 */
function whatsappResolverNumber(): AgentWhatsappNumber
{
    $agent = Agent::factory()->create();

    return AgentWhatsappNumber::create([
        'agent_id' => $agent->id,
        'access_token' => 'EAAG-token',
        'app_secret' => 'app-secret-value-1234567890',
        'phone_number_id' => '111222333444555',
        'waba_id' => '999888777666555',
        'webhook_secret' => Str::random(48),
        'enabled' => true,
    ]);
}

it('creates a Session + Conversation + mapping on first contact', function (): void {
    $number = whatsappResolverNumber();

    $chat = app(WhatsappChatResolver::class)->resolve($number, '4915551234567', 'Sam');

    expect($chat->agent_whatsapp_number_id)->toBe($number->id)
        ->and($chat->wa_id)->toBe('4915551234567')
        ->and($chat->name)->toBe('Sam')
        ->and($chat->chat_session_id)->not->toBeNull()
        ->and($chat->chat_conversation_id)->not->toBeNull();

    // The Session is real and belongs to the agent, with no chatter user.
    $session = $chat->session;
    expect($session->agent_id)->toBe($number->agent_id)
        ->and($session->user_id)->toBeNull()
        ->and($session->name)->toBe('Sam');
});

it('reuses the same mapping for a returning user', function (): void {
    $number = whatsappResolverNumber();
    $resolver = app(WhatsappChatResolver::class);

    $first = $resolver->resolve($number, '4915551234567', 'Sam');
    $second = $resolver->resolve($number, '4915551234567', 'Sam');

    expect($second->id)->toBe($first->id);
    expect(WhatsappChat::query()->count())->toBe(1);
});

it('keeps separate conversations for different users', function (): void {
    $number = whatsappResolverNumber();
    $resolver = app(WhatsappChatResolver::class);

    $a = $resolver->resolve($number, '111');
    $b = $resolver->resolve($number, '222');

    expect($a->id)->not->toBe($b->id)
        ->and($a->chat_conversation_id)->not->toBe($b->chat_conversation_id);
    expect(WhatsappChat::query()->count())->toBe(2);
});

it('tags the conversation as a WhatsApp conversation', function (): void {
    $number = whatsappResolverNumber();

    $chat = app(WhatsappChatResolver::class)->resolve($number, '4915551234567', 'Lee');

    $this->assertDatabaseHas('chat_conversations', [
        'external_id' => $chat->conversation->external_id,
        'source' => ConversationSource::Whatsapp->value,
    ]);
});

it('copes with a missing profile name', function (): void {
    $number = whatsappResolverNumber();

    $chat = app(WhatsappChatResolver::class)->resolve($number, '4915551234567');

    expect($chat->name)->toBeNull();
    expect(WhatsappChat::query()->count())->toBe(1);
});
