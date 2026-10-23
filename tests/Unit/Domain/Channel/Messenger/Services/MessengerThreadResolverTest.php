<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Domain\Channel\Messenger\Models\MessengerThread;
use Domain\Channel\Messenger\Services\MessengerThreadResolver;
use Domain\Conversation\Actions\Conversations\StartConversationAction;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Tests\Support\Channel\Messenger\FakeMessengerClient;

/**
 * The resolver maps a Messenger (page, psid) pair onto a persistent Chatvia
 * Session + Conversation, so a Messenger user keeps one ongoing conversation
 * across messages. First contact creates them (and looks the user's first
 * name up once); later messages reuse the same mapping.
 */
function messengerResolverPage(): AgentMessengerPage
{
    $agent = Agent::factory()->create();

    return AgentMessengerPage::create([
        'agent_id' => $agent->id,
        'page_access_token' => 'EAAG-token',
        'page_id' => '1234567890',
        'page_name' => 'Acme Support',
        'is_enabled' => true,
    ]);
}

function messengerResolver(FakeMessengerClient $client): MessengerThreadResolver
{
    return new MessengerThreadResolver(app(StartConversationAction::class), $client);
}

it('creates a Session + Conversation + mapping on first contact', function (): void {
    $page = messengerResolverPage();

    $thread = messengerResolver(new FakeMessengerClient)->resolve($page, '24680');

    expect($thread->agent_messenger_page_id)->toBe($page->id)
        ->and($thread->psid)->toBe('24680')
        ->and($thread->first_name)->toBe('Sam')
        ->and($thread->chat_session_id)->not->toBeNull()
        ->and($thread->chat_conversation_id)->not->toBeNull();

    // The Session is real and belongs to the agent, with no chatter user.
    $session = $thread->session;
    expect($session->agent_id)->toBe($page->agent_id)
        ->and($session->user_id)->toBeNull()
        ->and($session->name)->toBe('Sam');
});

it('reuses the same mapping for a returning user', function (): void {
    $page = messengerResolverPage();
    $resolver = messengerResolver(new FakeMessengerClient);

    $first = $resolver->resolve($page, '24680');
    $second = $resolver->resolve($page, '24680');

    expect($second->id)->toBe($first->id);
    expect(MessengerThread::query()->count())->toBe(1);
});

it('keeps separate conversations for different users', function (): void {
    $page = messengerResolverPage();
    $resolver = messengerResolver(new FakeMessengerClient);

    $a = $resolver->resolve($page, '100');
    $b = $resolver->resolve($page, '200');

    expect($a->id)->not->toBe($b->id)
        ->and($a->chat_conversation_id)->not->toBe($b->chat_conversation_id);
    expect(MessengerThread::query()->count())->toBe(2);
});

it('tags the conversation as a Messenger conversation', function (): void {
    $page = messengerResolverPage();

    $thread = messengerResolver(new FakeMessengerClient)->resolve($page, '24680');

    expect(Conversation::query()
        ->where('external_id', $thread->conversation->external_id)
        ->where('source', ConversationSource::Messenger)
        ->exists())->toBeTrue();
});

it('copes with a failed profile lookup', function (): void {
    $page = messengerResolverPage();

    $blindClient = new FakeMessengerClient;
    $blindClient->profile = null; // profile access restricted — still resolves.

    $thread = messengerResolver($blindClient)->resolve($page, '24680');

    expect($thread->first_name)->toBeNull();
    expect(MessengerThread::query()->count())->toBe(1);
});
