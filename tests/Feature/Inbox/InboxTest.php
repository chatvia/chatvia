<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Membership\Team\Services\TeamService;
use Inertia\Testing\AssertableInertia;

/**
 * The Inbox: one list of who is chatting right now across the agents a
 * person may view, with a flag for the chats they may join. Another
 * workspace's chats never show; joining stays Chat Widget only.
 */
function inboxChat(Agent $agent, array $attributes = []): Conversation
{
    $session = Session::factory()->create(['agent_id' => $agent->id]);

    return Conversation::factory()->withoutTitle()->create([
        'chat_session_id' => $session->id,
        'agent_id' => $agent->id,
        'source' => ConversationSource::Widget,
        'is_visible' => true,
        'last_activity_at' => now(),
        ...$attributes,
    ]);
}

function inboxOwner(): User
{
    $owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($owner);

    return $owner->refresh();
}

beforeEach(function (): void {
    $this->owner = inboxOwner();
    $this->support = makeTeamAgent($this->owner, 'Support');
    $this->sales = makeTeamAgent($this->owner, 'Sales');
});

it('renders the Inbox page', function (): void {
    $this->actingAs($this->owner)
        ->get(route('inbox'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('inbox/Index'));
});

it('lists chats across every agent, waiting ones first', function (): void {
    $online = inboxChat($this->support);
    $waiting = inboxChat($this->sales, ['handoff_status' => 'pending', 'last_activity_at' => now()->subHour()]);

    $this->actingAs($this->owner)
        ->getJson(route('inbox.conversations'))
        ->assertOk()
        ->assertJsonPath('waiting_count', 1)
        ->assertJsonPath('conversations.0.conversation_id', $waiting->external_id)
        ->assertJsonPath('conversations.0.agent.id', $this->sales->external_id)
        ->assertJsonPath('conversations.0.is_online', false)
        ->assertJsonPath('conversations.1.conversation_id', $online->external_id)
        ->assertJsonPath('conversations.1.is_online', true)
        ->assertJsonPath('conversations.1.can_join', true);
});

it('never shows another workspace\'s chats', function (): void {
    $stranger = inboxOwner();
    inboxChat(makeTeamAgent($stranger, 'Theirs'), ['handoff_status' => 'pending']);

    $this->actingAs($this->owner)
        ->getJson(route('inbox.conversations'))
        ->assertOk()
        ->assertJsonCount(0, 'conversations')
        ->assertJsonPath('waiting_count', 0);
});

it('shows a teammate only the agents their role may view', function (): void {
    $teammate = teammateWithPermissions($this->owner, ['agent.chat.view', 'agent.chat.takeover'], allAgents: false, agentExternalIds: [$this->sales->external_id]);
    inboxChat($this->support);
    $sales = inboxChat($this->sales);

    $this->actingAs($teammate)
        ->getJson(route('inbox.conversations'))
        ->assertOk()
        ->assertJsonCount(1, 'conversations')
        ->assertJsonPath('conversations.0.conversation_id', $sales->external_id)
        ->assertJsonPath('conversations.0.can_join', true);
});

it('lets nobody join outside the widget, with handoff off, or without the takeover permission', function (): void {
    inboxChat($this->support, ['source' => ConversationSource::HostedChat]);
    $this->sales->setSetting('handoff_enabled', false, 'widget');
    inboxChat($this->sales);

    $this->actingAs($this->owner)
        ->getJson(route('inbox.conversations'))
        ->assertOk()
        ->assertJsonPath('conversations.0.can_join', false)
        ->assertJsonPath('conversations.1.can_join', false);

    $viewer = teammateWithPermissions($this->owner, ['agent.chat.view']);

    $this->actingAs($viewer)
        ->getJson(route('inbox.conversations'))
        ->assertOk()
        ->assertJsonPath('conversations.0.can_join', false);
});

it('names the colleague who holds a chat to everyone but the holder', function (): void {
    $colleague = teammateWithPermissions($this->owner, ['agent.chat.view', 'agent.chat.takeover']);
    inboxChat($this->support, ['handoff_status' => 'human', 'handoff_user_id' => $colleague->id]);

    $this->actingAs($this->owner)
        ->getJson(route('inbox.conversations'))
        ->assertOk()
        ->assertJsonPath('conversations.0.holder_name', $colleague->name)
        ->assertJsonPath('conversations.0.held_by_me', false);

    $this->actingAs($colleague)
        ->getJson(route('inbox.conversations'))
        ->assertOk()
        ->assertJsonPath('conversations.0.held_by_me', true)
        ->assertJsonPath('conversations.0.holder_name', null)
        ->assertJsonPath('conversations.0.can_join', true);
});

it('counts the chats waiting for a human for the header badge', function (): void {
    inboxChat($this->support, ['handoff_status' => 'pending']);
    inboxChat($this->sales, ['handoff_status' => 'pending']);
    inboxChat($this->sales);

    $this->actingAs($this->owner)
        ->getJson(route('inbox.waiting-count'))
        ->assertOk()
        ->assertExactJson(['count' => 2]);
});
