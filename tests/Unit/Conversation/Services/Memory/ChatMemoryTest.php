<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\StartConversationAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Data\Memory\MessageData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session as ChatSession;
use Domain\Conversation\Services\Memory\CacheCipher;
use Domain\Conversation\Services\Memory\ChatCache;
use Domain\Conversation\Services\Memory\ChatMemory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * ChatMemory is the pure cache view of a visitor session. The conversation
 * list falls back to the database when the cache is cold (or stamped for a
 * different viewer) and is written back.
 */
beforeEach(function (): void {
    config(['cache.stores.chat' => ['driver' => 'array']]);
});

function chatMemoryConversation(ChatSession $session, string $title): Conversation
{
    return Conversation::create([
        'chat_session_id' => $session->id,
        'agent_id' => $session->agent_id,
        'external_id' => (string) Str::uuid(),
        'source' => ConversationSource::HostedChat,
        'title' => $title,
        'is_visible' => true,
    ]);
}

function chatMemoryFor(Agent $agent, ChatSession $session, ?int $viewerId = null): ChatMemory
{
    return app(ChatMemory::class)->withScope(new ChatScopeData($agent, $session->external_id, viewerId: $viewerId));
}

it('rebuilds the conversation list from the database when the cache is cold', function (): void {
    $agent = Agent::factory()->create();
    $session = ChatSession::create(['agent_id' => $agent->id]);
    chatMemoryConversation($session, 'Welcome');
    chatMemoryConversation($session, 'Pricing question');

    $list = chatMemoryFor($agent, $session)->visibleConversationsNewestFirst();

    expect($list)->toHaveCount(2)
        ->and(collect($list)->pluck('title')->all())->toEqualCanonicalizing(['Welcome', 'Pricing question']);
});

it('writes the rebuilt list back to the cache', function (): void {
    $agent = Agent::factory()->create();
    $session = ChatSession::create(['agent_id' => $agent->id]);
    chatMemoryConversation($session, 'Welcome');

    chatMemoryFor($agent, $session)->getConversationsForCurrentSession();

    // Values are encrypted at rest, and reads must carry the same
    // [workspace, agent, session] tag set the writes use.
    $raw = Cache::driver('chat')
        ->tags(ChatCache::sessionTags($agent->workspaceExternalId(), $agent->external_id, $session->external_id))
        ->get($session->external_id);
    expect($raw)->toBeString();

    $cached = CacheCipher::decrypt($raw);
    expect($cached['conversations'] ?? [])->toHaveCount(1)
        ->and($cached['conversations'][0]['title'])->toBe('Welcome');
});

it('keeps the history when a conversation is started with a cold cache', function (): void {
    // If starting a conversation read the empty cache without rebuilding
    // from the database first, the list would be overwritten with a single
    // entry and hide the older conversations.
    $agent = Agent::factory()->create();
    $session = ChatSession::create(['agent_id' => $agent->id]);
    chatMemoryConversation($session, 'Historical chat');

    Cache::driver('chat')
        ->tags(ChatCache::sessionTags($agent->workspaceExternalId(), $agent->external_id, $session->external_id))
        ->forget($session->external_id);

    $newId = app(StartConversationAction::class)->execute(
        new ChatScopeData($agent, $session->external_id),
        ConversationSource::HostedChat,
    );

    $list = chatMemoryFor($agent, $session)->getConversationsForCurrentSession();

    expect($list)->toHaveCount(2)
        ->and(collect($list)->pluck('title')->all())->toContain('Historical chat')
        ->and(collect($list)->pluck('id')->all())->toContain($newId);
});

it('rebuilds from the database when the cache was stamped for another viewer', function (): void {
    $agent = Agent::factory()->create();
    $userA = User::factory()->chatter(User::factory()->workspace()->create())->create();
    $userB = User::factory()->chatter(User::factory()->workspace()->create())->create();
    $session = ChatSession::create(['agent_id' => $agent->id, 'user_id' => $userB->id]);
    chatMemoryConversation($session, 'B-only chat');

    Cache::driver('chat')
        ->tags(ChatCache::sessionTags($agent->workspaceExternalId(), $agent->external_id, $session->external_id))
        ->forever($session->external_id, ['user_id' => $userA->id, 'conversations' => []]);

    $list = chatMemoryFor($agent, $session, $userB->id)->visibleConversationsNewestFirst();

    expect($list)->toHaveCount(1)
        ->and($list[0]['title'])->toBe('B-only chat');
});

it('makes a conversation visible when its first message is added', function (): void {
    $agent = Agent::factory()->create();
    $session = ChatSession::create(['agent_id' => $agent->id]);
    $conversationId = app(StartConversationAction::class)->execute(
        new ChatScopeData($agent, $session->external_id),
        ConversationSource::Widget,
    );

    $memory = chatMemoryFor($agent, $session);
    expect($memory->visibleConversationsNewestFirst())->toBe([]);

    $memory->setConversationId($conversationId)->appendMessage(new MessageData(
        id: (string) Str::uuid(),
        role: MessageRole::User,
        content: 'Hello',
        type: MessageData::TYPE_TEXT,
        timestamp: now()->timestamp,
    ));

    expect(collect($memory->visibleConversationsNewestFirst())->pluck('id')->all())->toBe([$conversationId])
        ->and($memory->getMessagesForConversation()[0]['role'])->toBe('user');
});

it('keeps the private LLM text out of the history but feeds it to the LLM', function (): void {
    $agent = Agent::factory()->create();
    $session = ChatSession::create(['agent_id' => $agent->id]);
    $memory = chatMemoryFor($agent, $session)->setConversationId((string) Str::uuid());

    $memory->appendMessage(new MessageData(
        id: 'm1',
        role: MessageRole::User,
        content: 'What is the price?',
        type: MessageData::TYPE_TEXT,
        timestamp: now()->timestamp,
        message_for_llm: 'price? [reference material]',
    ));

    expect($memory->getMessagesForConversation()[0]['message_for_llm'])->toBeNull()
        ->and($memory->getLlmMessages())->toBe([['role' => 'user', 'content' => 'price? [reference material]']])
        ->and($memory->llmTranscript(2))->toBe('price? [reference material]');
});
