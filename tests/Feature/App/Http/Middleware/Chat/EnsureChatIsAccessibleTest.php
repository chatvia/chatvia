<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\StartConversationAction;
use Domain\Conversation\Actions\Sessions\StartChatSessionAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Data\Memory\ChatViewerData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\Support\Conversation\HostedChatVisitor;

/**
 * On a protected agent a signed-in account needs access to that agent on every
 * hosted chat route, conversation routes included, and owning a conversation
 * on one agent never opens it on another. On a public agent of another
 * workspace a signed-in account is a guest.
 */
function ecaConversation(Agent $agent, Chatter $chatter): string
{
    $viewer = ChatViewerData::chatter($chatter->id);
    $sessionId = app(StartChatSessionAction::class)->execute($agent, $viewer);

    return app(StartConversationAction::class)->execute(
        new ChatScopeData($agent, $sessionId, viewer: $viewer),
        ConversationSource::HostedChat,
    )->external_id;
}

beforeEach(function (): void {
    $this->agent = createAgent(User::factory()->create(), ['slug' => Str::lower(Str::random(12)), 'is_public' => false]);
});

it('opens a conversation for its owner while they have access to the agent', function (): void {
    $chatter = Chatter::factory()->grantedTo($this->agent)->create();
    $conversationId = ecaConversation($this->agent, $chatter);

    $this->actingAs($chatter, 'chat')
        ->getJson("/chat/{$this->agent->slug}/conversations/{$conversationId}/messages")
        ->assertOk();
});

it('refuses an old conversation once the chatter\'s access to the agent is revoked', function (): void {
    $chatter = Chatter::factory()->grantedTo($this->agent)->create();
    $conversationId = ecaConversation($this->agent, $chatter);

    $chatter->agents()->detach($this->agent->id);

    $this->actingAs($chatter, 'chat')
        ->getJson("/chat/{$this->agent->slug}/conversations/{$conversationId}/messages")
        ->assertForbidden();

    $this->actingAs($chatter, 'chat')
        ->postJson("/chat/{$this->agent->slug}/stream/{$conversationId}", ['message' => 'Hello'])
        ->assertForbidden();
});

it('refuses a conversation of another agent on a private agent the chatter cannot use', function (): void {
    $chatter = Chatter::factory()->grantedTo($this->agent)->create();
    $conversationId = ecaConversation($this->agent, $chatter);
    $other = createAgent($this->agent->workspace->owner, ['slug' => Str::lower(Str::random(12)), 'is_public' => false]);

    $this->actingAs($chatter, 'chat')
        ->postJson("/chat/{$other->slug}/stream/{$conversationId}", ['message' => 'Hello'])
        ->assertForbidden();
});

it('never opens a conversation of one agent on another agent the chatter can use', function (): void {
    $chatter = Chatter::factory()->grantedTo($this->agent)->create();
    $conversationId = ecaConversation($this->agent, $chatter);
    $other = createAgent($this->agent->workspace->owner, ['slug' => Str::lower(Str::random(12)), 'is_public' => false]);
    $chatter->agents()->attach($other->id, ['workspace_id' => $other->workspace_id]);

    $this->actingAs($chatter, 'chat')
        ->getJson("/chat/{$other->slug}/conversations/{$conversationId}/messages")
        ->assertNotFound();

    $this->actingAs($chatter, 'chat')
        ->postJson("/chat/{$other->slug}/stream/{$conversationId}", ['message' => 'Hello'])
        ->assertNotFound();
});

it("chats as a guest on a public agent of another workspace, signed out of that workspace's chat", function (): void {
    $chatter = Chatter::factory()->grantedTo($this->agent)->create();
    $public = createAgent(User::factory()->create(), ['slug' => Str::lower(Str::random(12)), 'is_public' => true]);

    $this->actingAs($chatter, 'chat');
    $conversationId = HostedChatVisitor::startConversation($public);

    expect(Session::query()->where('agent_id', $public->id)->sole()->chatter_id)->toBeNull()
        ->and(Auth::guard('chat')->check())->toBeFalse()
        ->and($conversationId)->toBeUuid();
});

it('refuses a protected agent of another workspace to a signed-in chatter', function (): void {
    $chatter = Chatter::factory()->grantedTo($this->agent)->create();
    $protected = createAgent(User::factory()->create(), ['slug' => Str::lower(Str::random(12)), 'is_public' => false]);

    $this->actingAs($chatter, 'chat')
        ->postJson("/chat/{$protected->slug}/conversations/create", ['message' => 'Hello'])
        ->assertForbidden();
});
