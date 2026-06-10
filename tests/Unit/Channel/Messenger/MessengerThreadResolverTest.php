<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Domain\Channel\Messenger\Models\MessengerThread;
use Domain\Channel\Messenger\Services\MessengerThreadResolver;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\Memory\ChatMemory;
use Illuminate\Support\Str;
use Tests\Support\Messenger\FakeMessengerClient;

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
        'app_secret' => 'app-secret-value-1234567890',
        'page_id' => '1234567890',
        'page_name' => 'Acme Support',
        'webhook_secret' => Str::random(48),
        'enabled' => true,
    ]);
}

function messengerResolver(FakeMessengerClient $client): MessengerThreadResolver
{
    return new MessengerThreadResolver(app(ChatMemory::class), $client);
}

it('creates a Session + Conversation + mapping on first contact', function (): void {
    $page = messengerResolverPage();

    $thread = messengerResolver(new FakeMessengerClient)->resolve($page, '24680');

    expect($thread->agent_messenger_page_id)->toBe($page->id)
        ->and($thread->psid)->toBe('24680')
        ->and($thread->first_name)->toBe('Sam')
        ->and($thread->session_external_id)->not->toBeNull()
        ->and($thread->conversation_external_id)->not->toBeNull();

    // The Session is real and belongs to the agent, with no chatter user.
    $session = Session::query()->where('external_id', $thread->session_external_id)->firstOrFail();
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
        ->and($a->conversation_external_id)->not->toBe($b->conversation_external_id);
    expect(MessengerThread::query()->count())->toBe(2);
});

it('tags the conversation as a Messenger conversation', function (): void {
    $page = messengerResolverPage();

    $thread = messengerResolver(new FakeMessengerClient)->resolve($page, '24680');

    $this->assertDatabaseHas('chat_conversations', [
        'external_id' => $thread->conversation_external_id,
        'source' => ConversationSource::Messenger->value,
    ]);
});

it('copes with a failed profile lookup', function (): void {
    $page = messengerResolverPage();

    $blindClient = new FakeMessengerClient;
    $blindClient->profile = null; // profile access restricted — still resolves.

    $thread = messengerResolver($blindClient)->resolve($page, '24680');

    expect($thread->first_name)->toBeNull();
    expect(MessengerThread::query()->count())->toBe(1);
});
