<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Data\Memory\ChatViewerData;
use Domain\Conversation\Data\Memory\LlmMessageData;
use Domain\Conversation\Data\Memory\MessageData;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session as ChatSession;
use Domain\Conversation\Services\Memory\ChatCache;
use Domain\Conversation\Services\Memory\ChatMemory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ChatMemory is the pure cache view of a visitor session. The conversation
 * list falls back to the database when the cache is cold (or stamped for a
 * different viewer, by kind and id) and is written back; the private text
 * meant for the model never shows in the history.
 */
function chatMemoryConversation(ChatSession $session, string $title): Conversation
{
    return Conversation::factory()->inSession($session)->hostedChat()->visible()->withTitle($title)->create();
}

function chatMemoryFor(Agent $agent, ChatSession $session, ?ChatViewerData $viewer = null): ChatMemory
{
    return app(ChatMemory::class)->withScope(new ChatScopeData($agent, $session->external_id, viewer: $viewer));
}

it('rebuilds the conversation list from the database when the cache is cold, and writes it back', function (): void {
    $agent = Agent::factory()->create();
    $session = ChatSession::factory()->create(['agent_id' => $agent->id]);
    $welcome = chatMemoryConversation($session, 'Welcome');
    chatMemoryConversation($session, 'Pricing question');

    $list = chatMemoryFor($agent, $session)->visibleConversationsNewestFirst();
    DB::table('chat_conversations')->where('id', $welcome->id)->delete();

    expect(collect($list)->pluck('title')->all())->toEqualCanonicalizing(['Welcome', 'Pricing question'])
        ->and(collect(chatMemoryFor($agent, $session)->getConversationsForCurrentSession())->pluck('title')->all())->toEqualCanonicalizing(['Welcome', 'Pricing question']);
});

it('serves a cached list only to the viewer it was stamped for, by kind and id, and rebuilds for anyone else', function (): void {
    $agent = Agent::factory()->create();
    $chatterA = Chatter::factory()->grantedTo($agent)->create();
    $chatterB = Chatter::factory()->grantedTo($agent)->create();
    $otherChatterSession = ChatSession::factory()->create(['agent_id' => $agent->id, 'chatter_id' => $chatterB->id]);
    chatMemoryConversation($otherChatterSession, 'B-only chat');
    $staffKindSession = ChatSession::factory()->create(['agent_id' => $agent->id, 'chatter_id' => $chatterA->id]);
    chatMemoryConversation($staffKindSession, 'Chatter chat');
    $stamp = fn (ChatSession $session, ChatViewerData $viewer) => Cache::driver('chat')
        ->tags(ChatCache::sessionTags($agent->workspaceExternalId(), $agent->external_id, $session->external_id))
        ->forever($session->external_id, ['viewer' => $viewer->key(), 'conversations' => []]);
    $stamp($otherChatterSession, ChatViewerData::chatter($chatterA->id));
    $stamp($staffKindSession, ChatViewerData::staff($chatterA->id));
    $staff = User::factory()->create();
    $staffSession = ChatSession::factory()->forUser($staff)->create(['agent_id' => $agent->id]);
    $staffConversation = chatMemoryConversation($staffSession, 'Staff chat');
    chatMemoryFor($agent, $staffSession, ChatViewerData::staff($staff->id))->getConversationsForCurrentSession();
    DB::table('chat_conversations')->where('id', $staffConversation->id)->delete();

    expect(collect(chatMemoryFor($agent, $otherChatterSession, ChatViewerData::chatter($chatterB->id))->visibleConversationsNewestFirst())->pluck('title')->all())->toBe(['B-only chat'])
        ->and(collect(chatMemoryFor($agent, $staffKindSession, ChatViewerData::chatter($chatterA->id))->visibleConversationsNewestFirst())->pluck('title')->all())->toBe(['Chatter chat'])
        ->and(chatMemoryFor($agent, $staffSession, ChatViewerData::staff($staff->id))->getConversationsForCurrentSession())->toHaveCount(1)
        ->and(chatMemoryFor($agent, $staffSession, ChatViewerData::chatter($staff->id))->getConversationsForCurrentSession())->toBe([]);
});

it('keeps the private LLM text out of the history but feeds it to the LLM', function (): void {
    $agent = Agent::factory()->create();
    $session = ChatSession::factory()->create(['agent_id' => $agent->id]);
    $memory = chatMemoryFor($agent, $session)->setConversationId((string) Str::uuid7());

    $memory->appendMessage(new MessageData(
        id: 'm1',
        role: MessageRole::User,
        content: 'What is the price?',
        type: MessageData::TYPE_TEXT,
        timestamp: now()->timestamp,
        message_for_llm: 'price? [reference material]',
    ));

    expect($memory->getMessagesForConversation()[0]->message_for_llm)->toBeNull()
        ->and(array_map(static fn (LlmMessageData $message): array => $message->toWire(), $memory->getLlmMessages()))
        ->toBe([['role' => 'user', 'content' => 'price? [reference material]']])
        ->and($memory->llmTranscript(2))->toBe('price? [reference material]');
});
