<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Membership\Team\Actions\Roles\CreateTeamRoleAction;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Models\Team;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Pins the broadcast-channel authorization callbacks in routes/channels.php
 * through the real POST /broadcasting/auth endpoint.
 *
 * The test env broadcasts over the `log` driver, whose auth() is a no-op that
 * never runs the channel callbacks (every subscription would 200). beforeEach
 * therefore re-registers the booted channels on the reverb (Pusher-protocol)
 * broadcaster and makes it the default — its auth() enforces the callbacks
 * and signs the response locally, so no socket traffic is involved.
 */
function broadcastAuthAs(User $user, string $channelName): TestResponse
{
    return test()->actingAs($user)->post('/broadcasting/auth', [
        'channel_name' => $channelName,
        'socket_id' => '123.456',
    ]);
}

/**
 * An employee on the given team whose role either can or cannot access the
 * team's agents (allAgents toggles the agents.access-all grant).
 */
function teammateOnTeam(User $owner, Team $team, bool $allAgents): User
{
    $role = app(CreateTeamRoleAction::class)->execute(new SaveTeamRoleData(
        team: $team,
        name: 'Role-'.Str::random(8),
        permissions: [],
        allAgents: $allAgents,
        agentExternalIds: [],
    ));

    $member = User::factory()->employee($owner)->create(['current_team_id' => $team->id]);
    $team->members()->attach($member->id, ['role_id' => $role->id]);

    return $member;
}

beforeEach(function (): void {
    Broadcast::driver()->getChannels()->each(
        fn (callable $callback, string $channel) => Broadcast::driver('reverb')->channel($channel, $callback),
    );
    config(['broadcasting.default' => 'reverb']);

    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner);
});

it('denies unauthenticated subscription attempts', function (): void {
    $this->post('/broadcasting/auth', [
        'channel_name' => "private-agents.{$this->agent->external_id}.files.progress",
        'socket_id' => '123.456',
    ])->assertForbidden();
});

describe('agent-scoped private channels', function (): void {
    it('authorizes the owning workspace', function (): void {
        broadcastAuthAs($this->owner, "private-agents.{$this->agent->external_id}.files.progress")
            ->assertOk()
            ->assertJsonStructure(['auth']);
    });

    it('authorizes a teammate whose role grants agent access', function (): void {
        $teammate = teammateOnTeam($this->owner, $this->agent->team, allAgents: true);

        broadcastAuthAs($teammate, "private-agents.{$this->agent->external_id}.crawler")
            ->assertOk()
            ->assertJsonStructure(['auth']);
    });

    it('denies a teammate whose role grants no agent access', function (): void {
        $teammate = teammateOnTeam($this->owner, $this->agent->team, allAgents: false);

        broadcastAuthAs($teammate, "private-agents.{$this->agent->external_id}.crawler")
            ->assertForbidden();
    });

    it('denies an unrelated workspace', function (): void {
        $outsider = User::factory()->create();

        broadcastAuthAs($outsider, "private-agents.{$this->agent->external_id}.files.progress")
            ->assertForbidden();
    });

    it('denies a nonexistent agent', function (): void {
        broadcastAuthAs($this->owner, 'private-agents.no-such-agent.files.progress')
            ->assertForbidden();
    });
});

describe('the per-user notification channel', function (): void {
    it('authorizes the owning user', function (): void {
        broadcastAuthAs($this->owner, "private-App.Identity.Workspace.Models.User.{$this->owner->id}")
            ->assertOk()
            ->assertJsonStructure(['auth']);
    });

    it('denies another user', function (): void {
        $other = User::factory()->create();

        broadcastAuthAs($other, "private-App.Identity.Workspace.Models.User.{$this->owner->id}")
            ->assertForbidden();
    });
});

describe('the chat presence channel', function (): void {
    it('authorizes a workspace member on a conversation of their agent', function (): void {
        // Source + title pinned: the factory's random pick can land on a case
        // newer than the chat_conversations source CHECK in the seeded test
        // DB, and a random title can overflow its stale varchar(255) once
        // encrypted.
        $conversation = Conversation::factory()->create([
            'chat_session_id' => Session::factory()->create(['agent_id' => $this->agent->id])->id,
            'source' => ConversationSource::Widget,
            'title' => null,
        ]);

        broadcastAuthAs($this->owner, "presence-chat.{$conversation->external_id}")
            ->assertOk()
            ->assertJsonStructure(['auth', 'channel_data']);
    });

    it('denies a conversation belonging to another workspace agent', function (): void {
        // The factory chain provisions its own session → agent → workspace.
        $foreign = Conversation::factory()->create(['source' => ConversationSource::Widget, 'title' => null]);

        broadcastAuthAs($this->owner, "presence-chat.{$foreign->external_id}")
            ->assertForbidden();
    });

    it('denies a nonexistent conversation', function (): void {
        broadcastAuthAs($this->owner, 'presence-chat.'.Str::uuid7()->toString())
            ->assertForbidden();
    });
});
