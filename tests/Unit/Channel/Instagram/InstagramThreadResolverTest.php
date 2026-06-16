<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Instagram\Models\AgentInstagramAccount;
use Domain\Channel\Instagram\Models\InstagramThread;
use Domain\Channel\Instagram\Services\InstagramThreadResolver;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\Memory\ChatMemory;
use Illuminate\Support\Str;
use Tests\Support\Instagram\FakeInstagramClient;

/**
 * The resolver maps an Instagram (account, igsid) pair onto a persistent
 * Chatvia Session + Conversation, so an Instagram user keeps one ongoing
 * conversation across messages. First contact creates them (and looks the
 * user's profile up once); later messages reuse the same mapping.
 */
function instagramResolverAccount(): AgentInstagramAccount
{
    $agent = Agent::factory()->create();

    return AgentInstagramAccount::create([
        'agent_id' => $agent->id,
        'page_access_token' => 'IGAA-token-value-1234567890',
        'page_id' => '998877',
        'ig_id' => '17840012345',
        'username' => 'acme.support',
        'webhook_secret' => Str::random(48),
        'enabled' => true,
    ]);
}

function instagramResolver(FakeInstagramClient $client): InstagramThreadResolver
{
    return new InstagramThreadResolver(app(ChatMemory::class), $client);
}

it('creates a Session + Conversation + mapping on first contact', function (): void {
    $account = instagramResolverAccount();

    $thread = instagramResolver(new FakeInstagramClient)->resolve($account, '5550001234');

    expect($thread->agent_instagram_account_id)->toBe($account->id)
        ->and($thread->igsid)->toBe('5550001234')
        ->and($thread->name)->toBe('Sam')
        ->and($thread->chat_session_id)->not->toBeNull()
        ->and($thread->chat_conversation_id)->not->toBeNull();

    // The Session is real and belongs to the agent, with no chatter user.
    $session = $thread->session;
    expect($session->agent_id)->toBe($account->agent_id)
        ->and($session->user_id)->toBeNull()
        ->and($session->name)->toBe('Sam');
});

it('reuses the same mapping for a returning user', function (): void {
    $account = instagramResolverAccount();
    $resolver = instagramResolver(new FakeInstagramClient);

    $first = $resolver->resolve($account, '5550001234');
    $second = $resolver->resolve($account, '5550001234');

    expect($second->id)->toBe($first->id);
    expect(InstagramThread::query()->count())->toBe(1);
});

it('keeps separate conversations for different users', function (): void {
    $account = instagramResolverAccount();
    $resolver = instagramResolver(new FakeInstagramClient);

    $a = $resolver->resolve($account, '100');
    $b = $resolver->resolve($account, '200');

    expect($a->id)->not->toBe($b->id)
        ->and($a->chat_conversation_id)->not->toBe($b->chat_conversation_id);
    expect(InstagramThread::query()->count())->toBe(2);
});

it('tags the conversation as an Instagram conversation', function (): void {
    $account = instagramResolverAccount();

    $thread = instagramResolver(new FakeInstagramClient)->resolve($account, '5550001234');

    $this->assertDatabaseHas('chat_conversations', [
        'external_id' => $thread->conversation->external_id,
        'source' => ConversationSource::Instagram->value,
    ]);
});

it('falls back to the @username and copes with a failed profile lookup', function (): void {
    $account = instagramResolverAccount();

    $usernameOnly = new FakeInstagramClient;
    $usernameOnly->profile = ['name' => null, 'username' => 'sam.codes'];
    $withUsername = instagramResolver($usernameOnly)->resolve($account, '111');

    $blindClient = new FakeInstagramClient;
    $blindClient->profile = null; // profile lookup failed — still resolves.
    $blind = instagramResolver($blindClient)->resolve($account, '222');

    expect($withUsername->name)->toBe('@sam.codes')
        ->and($blind->name)->toBeNull();
    expect(InstagramThread::query()->count())->toBe(2);
});
