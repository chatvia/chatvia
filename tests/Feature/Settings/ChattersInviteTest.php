<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Membership\Agent\Events\AgentChatterInvited;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->agent = Agent::factory()->create(['user_id' => $this->owner->id, 'is_public' => false]);
});

it('invites a member and grants the selected agent', function (): void {
    Event::fake([AgentChatterInvited::class]);

    $this->actingAs($this->owner)
        ->post(route('people.chatters.invite'), [
            'email' => 'newbie@example.com',
            'agents' => [$this->agent->external_id],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $chatter = User::query()->where('email', 'newbie@example.com')->first();

    expect($chatter)->not->toBeNull()
        ->and($this->agent->chatters()->whereKey($chatter->id)->exists())->toBeTrue();

    Event::assertDispatched(AgentChatterInvited::class);
});

it('requires at least one agent', function (): void {
    $this->actingAs($this->owner)
        ->post(route('people.chatters.invite'), [
            'email' => 'newbie@example.com',
            'agents' => [],
        ])
        ->assertSessionHasErrors('agents');

    expect(User::query()->where('email', 'newbie@example.com')->exists())->toBeFalse();
});

it('rejects a public agent (not invitable)', function (): void {
    $public = Agent::factory()->create(['user_id' => $this->owner->id, 'is_public' => true]);

    $this->actingAs($this->owner)
        ->post(route('people.chatters.invite'), [
            'email' => 'newbie@example.com',
            'agents' => [$public->external_id],
        ])
        ->assertSessionHasErrors('agents');
});

it('rejects an agent from another workspace', function (): void {
    $otherOwner = User::factory()->create();
    $foreignAgent = Agent::factory()->create(['user_id' => $otherOwner->id, 'is_public' => false]);

    $this->actingAs($this->owner)
        ->post(route('people.chatters.invite'), [
            'email' => 'newbie@example.com',
            'agents' => [$foreignAgent->external_id],
        ])
        ->assertSessionHasErrors('agents');
});
