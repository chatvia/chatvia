<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\StartConversationAction;
use Domain\Conversation\Actions\Sessions\StartChatSessionAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Data\Memory\ChatViewerData;
use Domain\Conversation\Data\Memory\MessageData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\GeneratedMedia;
use Illuminate\Support\Str;
use Tests\Support\Conversation\HostedChatVisitor;

/**
 * The hosted chat loads the messages of a conversation of the visitor's own
 * chat session. Staff (`chat_staff`) and chatters (`chat`) come from two
 * tables and can share an id, so an equal id never opens the other's
 * conversation.
 */
it('returns the messages of the visitor\'s conversation', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $id = HostedChatVisitor::startConversation($agent, 'Where is my order?');

    $this->getJson("/chat/{$agent->slug}/conversations/{$id}/messages")
        ->assertOk()
        ->assertJsonPath('0.content', 'Where is my order?')
        ->assertJsonPath('0.role', 'user');
});

it('never lets a chatter open a staff member\'s conversation when their ids are equal', function (): void {
    $agent = createAgent(User::factory()->create(), ['slug' => Str::lower(Str::random(12)), 'is_public' => false]);
    // One id taken from both id sequences, so neither table already holds it.
    $id = sharedStaffAndChatterId();
    // A teammate whose role reaches the agent, so they may chat with it.
    $team = ownerTeam($agent->workspace->owner);
    $staff = User::factory()->employee($agent->workspace->owner)->create(['id' => $id, 'current_team_id' => $team->id]);
    $team->members()->attach($staff->id, ['role_id' => teamRole($team)->id]);
    $chatter = Chatter::factory()->grantedTo($agent)->create(['id' => $id]);
    $viewer = ChatViewerData::staff($staff->id);

    $sessionId = app(StartChatSessionAction::class)->execute($agent, $viewer);
    $conversationId = app(StartConversationAction::class)->execute(
        new ChatScopeData($agent, $sessionId, viewer: $viewer),
        ConversationSource::HostedChat,
    )->external_id;

    $url = "/chat/{$agent->slug}/conversations/{$conversationId}/messages";

    $this->actingAs($chatter, 'chat')->getJson($url)->assertNotFound();

    app('auth')->forgetGuards();

    $this->actingAs($staff, 'chat_staff')->getJson($url)->assertOk();
});

it('shows a generated image as it stands now, not as the reply stored it', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $id = HostedChatVisitor::startConversation($agent, 'Draw a bicycle');
    $media = GeneratedMedia::factory()->forConversation(Conversation::query()->where('external_id', $id)->firstOrFail())->ready()->create();
    HostedChatVisitor::memory($agent, $id)->appendMessage(new MessageData(
        id: (string) Str::uuid7(),
        role: MessageRole::Assistant,
        content: 'Here it is.',
        type: MessageData::TYPE_TEXT,
        timestamp: now()->timestamp,
        metadata: ['artifacts' => [['kind' => 'image', 'data' => ['id' => $media->external_id, 'status' => 'pending']]]],
    ));

    $this->getJson("/chat/{$agent->slug}/conversations/{$id}/messages")
        ->assertOk()
        ->assertJsonPath('1.metadata.artifacts.0.data.status', 'ready')
        ->assertJsonPath('1.metadata.artifacts.0.data.url', $media->url);
});
