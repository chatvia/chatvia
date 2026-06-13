<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session as ChatSession;
use Domain\Conversation\Services\Memory\CacheCipher;
use Domain\Conversation\Services\Memory\ChatCache;
use Domain\Conversation\Services\Memory\ConversationManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config(['cache.stores.chat' => ['driver' => 'array']]);
});

function makeConversationManager(ChatSession $session, Agent $agent): ConversationManager
{
    return new ConversationManager(
        new ChatCache($session->external_id, $agent),
        $session->external_id,
    );
}

it('rebuilds the conversation list from DB when the cache is cold', function (): void {
    // After TurnState reattaches a chatter to their old session row, the
    // Redis entry may be evicted (long absence) or never warmed in the
    // current process. The sidebar must still render from chat_conversations.
    $agent = Agent::factory()->create();
    $session = ChatSession::create(['agent_id' => $agent->id]);

    Conversation::create([
        'chat_session_id' => $session->id,
        'agent_id' => $session->agent_id,
        'external_id' => (string) Str::uuid(),
        'source' => ConversationSource::HostedChat,
        'title' => 'Welcome',
        'is_visible' => true,
    ]);
    Conversation::create([
        'chat_session_id' => $session->id,
        'agent_id' => $session->agent_id,
        'external_id' => (string) Str::uuid(),
        'source' => ConversationSource::HostedChat,
        'title' => 'Pricing question',
        'is_visible' => true,
    ]);

    $list = makeConversationManager($session, $agent)
        ->getConversationsForSession(onlyVisible: true, orderByDate: true);

    expect($list)->toHaveCount(2)
        ->and(collect($list)->pluck('title')->all())
        ->toEqualCanonicalizing(['Welcome', 'Pricing question']);
});

it('reprimes the cache after rebuilding from DB', function (): void {
    $agent = Agent::factory()->create();
    $session = ChatSession::create(['agent_id' => $agent->id]);
    Conversation::create([
        'chat_session_id' => $session->id,
        'agent_id' => $session->agent_id,
        'external_id' => (string) Str::uuid(),
        'source' => ConversationSource::HostedChat,
        'title' => 'Welcome',
        'is_visible' => true,
    ]);

    makeConversationManager($session, $agent)->getConversationsForSession();

    // ChatCache encrypts every value at rest (CacheCipher), so the raw store
    // holds a ciphertext string — decrypt before asserting the structure.
    // Reads must carry the same [workspace, agent, session] tag set the writes
    // use (see ChatCache::sessionTags) or the namespace diverges and this misses.
    $raw = Cache::driver('chat')
        ->tags(ChatCache::sessionTags($agent->workspaceExternalId(), $agent->external_id, $session->external_id))
        ->get($session->external_id);
    expect($raw)->toBeString();

    $cached = CacheCipher::decrypt($raw);
    expect($cached['conversations'] ?? [])->toHaveCount(1)
        ->and($cached['conversations'][0]['title'])->toBe('Welcome');
});

it('preserves history when a new conversation is created with a cold cache', function (): void {
    // Regression: after TurnState reattaches a chatter, the cache slot may
    // still be cold. If createConversation reads the empty cache without
    // rebuilding from DB first, the cache gets overwritten with a single
    // entry — masking historical conversations until eviction.
    $agent = Agent::factory()->create();
    $session = ChatSession::create(['agent_id' => $agent->id]);

    Conversation::create([
        'chat_session_id' => $session->id,
        'agent_id' => $session->agent_id,
        'external_id' => (string) Str::uuid(),
        'source' => ConversationSource::HostedChat,
        'title' => 'Historical chat',
        'is_visible' => true,
    ]);

    $manager = makeConversationManager($session, $agent);

    // Cold cache: simulate by ensuring no key is set under [workspace, agent, session].
    Cache::driver('chat')
        ->tags(ChatCache::sessionTags($agent->workspaceExternalId(), $agent->external_id, $session->external_id))
        ->forget($session->external_id);

    $newId = $manager->createConversation('New Chat', ConversationSource::HostedChat);

    $list = $manager->getConversationsForSession();
    $titles = collect($list)->pluck('title')->all();

    expect($list)->toHaveCount(2)
        ->and($titles)->toContain('Historical chat')
        ->and(collect($list)->pluck('id')->all())->toContain($newId);
});

it('falls back to DB when the cache is stamped with a different user_id', function (): void {
    // Defensive: a stale cache slot stamped under user A used to silently
    // return [] for user B. Now we rebuild from DB so B sees their own
    // session's conversations.
    $agent = Agent::factory()->create();
    $userA = User::factory()->chatter(User::factory()->workspace()->create())->create();
    $userB = User::factory()->chatter(User::factory()->workspace()->create())->create();
    $session = ChatSession::create(['agent_id' => $agent->id, 'user_id' => $userB->id]);

    Conversation::create([
        'chat_session_id' => $session->id,
        'agent_id' => $session->agent_id,
        'external_id' => (string) Str::uuid(),
        'source' => ConversationSource::HostedChat,
        'title' => 'B-only chat',
        'is_visible' => true,
    ]);

    Cache::driver('chat')
        ->tags(ChatCache::sessionTags($agent->workspaceExternalId(), $agent->external_id, $session->external_id))
        ->forever(
            $session->external_id,
            ['user_id' => $userA->id, 'conversations' => []]
        );

    Auth::guard('web')->login($userB);

    $list = makeConversationManager($session, $agent)
        ->getConversationsForSession(onlyVisible: true);

    expect($list)->toHaveCount(1)
        ->and($list[0]['title'])->toBe('B-only chat');
});
