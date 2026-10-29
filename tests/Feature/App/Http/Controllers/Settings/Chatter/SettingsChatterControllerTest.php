<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\MessageLimit;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Membership\Agent\Events\AgentChatterInvited;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

/**
 * Workspace Members page, the chatters (SettingsChatterController): inviting a
 * chatter with the agents they may use, and a chatter's access controls (the
 * monthly message cap on their own `message_limits.chatter_id` row and the
 * deactivation switch). The chatter is created inside the inviting workspace
 * only; an email a chatter of another workspace uses is invited like any new
 * email, and the answer never hints that it exists elsewhere. A teammate only
 * offers and grants the agents their team role reaches.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    ownerTeam($this->owner);
    $this->agent = Agent::factory()->create(['workspace_id' => $this->owner->workspace()->id, 'is_public' => false]);
});

it('invites an email a chatter of another workspace uses as a separate chatter, without saying so', function (): void {
    Event::fake([AgentChatterInvited::class]);
    $foreign = Chatter::factory()->grantedTo(Agent::factory()->create(['is_public' => false]))->create([
        'email' => 'shared@example.com',
        'password' => Hash::make('foreign-pass'),
    ]);

    $this->actingAs($this->owner)
        ->post(route('people.chatters.invite'), [
            'email' => 'Shared@Example.com',
            'agents' => [$this->agent->external_id],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('invitation.email', 'shared@example.com');

    $chatter = $this->owner->workspace()->chatters()->where('email', 'shared@example.com')->sole();

    expect(array_keys(session('invitation')))->toBe(['email', 'accept_url', 'expires_at'])
        ->and($chatter->id)->not->toBe($foreign->id)
        ->and($this->agent->chatters()->whereKey($chatter->id)->exists())->toBeTrue()
        ->and($this->agent->chatters()->whereKey($foreign->id)->exists())->toBeFalse()
        ->and(Hash::check('foreign-pass', $foreign->fresh()->password))->toBeTrue()
        ->and(User::query()->where('email', 'shared@example.com')->exists())->toBeFalse();
    Event::assertDispatched(AgentChatterInvited::class);
});

it('refuses an invitation without an agent the workspace may invite to', function (string $agents): void {
    $chosen = match ($agents) {
        'public' => [Agent::factory()->create(['workspace_id' => $this->owner->workspace()->id, 'is_public' => true])->external_id],
        'foreign' => [Agent::factory()->create(['is_public' => false])->external_id],
    };

    $this->actingAs($this->owner)
        ->post(route('people.chatters.invite'), ['email' => 'newbie@example.com', 'agents' => $chosen])
        ->assertSessionHasErrors('agents');

    expect(Chatter::query()->where('email', 'newbie@example.com')->exists())->toBeFalse();
})->with([
    'a public agent' => 'public',
    "another workspace's agent" => 'foreign',
]);

it('offers and grants a teammate only the agents their team role reaches', function (): void {
    $otherAgent = Agent::factory()->create(['workspace_id' => $this->owner->workspace()->id, 'team_id' => ownerTeam($this->owner)->id, 'is_public' => false, 'name' => 'Zed']);
    $this->agent->forceFill(['team_id' => ownerTeam($this->owner)->id, 'name' => 'Abe'])->save();
    $teammate = teammateWithPermissions($this->owner, ['account.chatters.manage'], allAgents: false, agentExternalIds: [$this->agent->external_id]);

    $this->actingAs($teammate)
        ->get(route('people.chatters.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('agents', [['external_id' => $this->agent->external_id, 'name' => 'Abe']]));

    $this->actingAs($teammate)
        ->post(route('people.chatters.invite'), ['email' => 'newbie@example.com', 'agents' => [$this->agent->external_id, $otherAgent->external_id]])
        ->assertSessionHasErrors('agents');

    expect(Chatter::query()->where('email', 'newbie@example.com')->exists())->toBeFalse();
});

it('sets a chatter\'s message limit and deactivation on the chatter\'s own row', function (): void {
    $chatter = Chatter::factory()->ofWorkspace($this->owner)->create(['name' => 'Casey']);

    $this->actingAs($this->owner)
        ->patchJson(route('people.chatters.update', ['chatter' => $chatter->external_id]), [
            'messages_limit' => 500,
            'deactivated' => true,
            'deactivation_reason' => 'Abuse',
        ])
        ->assertOk()
        ->assertJsonPath('messages_limit', 500)
        ->assertJsonPath('messages_used', 0)
        ->assertJsonPath('is_deactivated', true)
        ->assertJsonPath('deactivation_reason', 'Abuse');

    expect(MessageLimit::query()->where('chatter_id', $chatter->id)->value('monthly_limit'))->toBe(500)
        ->and(MessageLimit::query()->where('workspace_id', $this->owner->workspace()->id)->exists())->toBeFalse()
        ->and($chatter->fresh()->isDeactivated())->toBeTrue()
        ->and($this->owner->fresh()->isDeactivated())->toBeFalse();
});
