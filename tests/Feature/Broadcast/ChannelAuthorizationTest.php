<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
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

beforeEach(function (): void {
    Broadcast::driver()->getChannels()->each(
        fn (callable $callback, string $channel): mixed => Broadcast::driver('reverb')->channel($channel, $callback),
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

// Every agent channel in routes/channels.php: each one answers to agent access.
dataset('agent channels', ['crawler', 'crawler.progress', 'files.progress', 'texts.progress', 'ingestion.text', 'web.ingestion.url', 'evals']);

describe('agent-scoped private channels', function (): void {
    it('authorizes the owning workspace', function (string $channel): void {
        broadcastAuthAs($this->owner, "private-agents.{$this->agent->external_id}.{$channel}")
            ->assertOk()
            ->assertJsonStructure(['auth']);
    })->with('agent channels');

    it('authorizes a teammate whose role grants agent access', function (string $channel): void {
        $teammate = teammateWithPermissions($this->owner, [], $this->agent->team);

        broadcastAuthAs($teammate, "private-agents.{$this->agent->external_id}.{$channel}")
            ->assertOk()
            ->assertJsonStructure(['auth']);
    })->with('agent channels');

    it('denies a teammate whose role grants no agent access', function (string $channel): void {
        $teammate = teammateWithPermissions($this->owner, [], $this->agent->team, allAgents: false);

        broadcastAuthAs($teammate, "private-agents.{$this->agent->external_id}.{$channel}")
            ->assertForbidden();
    })->with('agent channels');

    it('denies an unrelated workspace', function (string $channel): void {
        $outsider = User::factory()->create();

        broadcastAuthAs($outsider, "private-agents.{$this->agent->external_id}.{$channel}")
            ->assertForbidden();
    })->with('agent channels');

    it('denies a nonexistent agent', function (string $channel): void {
        broadcastAuthAs($this->owner, "private-agents.no-such-agent.{$channel}")
            ->assertForbidden();
    })->with('agent channels');
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
        $conversation = Conversation::factory()->create([
            'chat_session_id' => Session::factory()->create(['agent_id' => $this->agent->id])->id,
        ]);

        broadcastAuthAs($this->owner, "presence-chat.{$conversation->external_id}")
            ->assertOk()
            ->assertJsonStructure(['auth', 'channel_data']);
    });

    it('denies a conversation belonging to another workspace agent', function (): void {
        // The factory chain provisions its own session → agent → workspace.
        $foreign = Conversation::factory()->create();

        broadcastAuthAs($this->owner, "presence-chat.{$foreign->external_id}")
            ->assertForbidden();
    });

    it('denies a nonexistent conversation', function (): void {
        broadcastAuthAs($this->owner, 'presence-chat.'.Str::uuid7()->toString())
            ->assertForbidden();
    });
});
