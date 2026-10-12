<?php

/**
 * Workspace Members page: inviting a chatter (POST people.chatters.invite).
 * The chatter is created inside the inviting workspace only; an email already
 * used by a chatter of another workspace, or by a staff account, is invited
 * like any new email, and the response never hints that it exists elsewhere.
 */

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Membership\Agent\Events\AgentChatterInvited;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->agent = Agent::factory()->create(['workspace_id' => $this->owner->workspace()->id, 'is_public' => false]);
});

it('invites a member and grants the selected agent', function (): void {
    Event::fake([AgentChatterInvited::class]);

    $this->actingAs($this->owner)
        ->post(route('people.chatters.invite'), [
            'email' => 'newbie@example.com',
            'agents' => [$this->agent->external_id],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('invitation.email', 'newbie@example.com');

    $chatter = $this->owner->workspace()->chatters()->where('email', 'newbie@example.com')->first();

    expect($chatter)->toBeInstanceOf(Chatter::class)
        ->and($this->agent->chatters()->whereKey($chatter->id)->exists())->toBeTrue()
        ->and(User::query()->where('email', 'newbie@example.com')->exists())->toBeFalse();

    Event::assertDispatched(AgentChatterInvited::class);
});

it('invites an email that is a chatter of another workspace as a separate chatter, without saying so', function (): void {
    Event::fake([AgentChatterInvited::class]);

    $otherOwner = User::factory()->create();
    $otherAgent = Agent::factory()->create(['workspace_id' => $otherOwner->workspace()->id, 'is_public' => false]);
    $foreign = Chatter::factory()->grantedTo($otherAgent)->create([
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

    // The flash is the same shape as for a brand-new email.
    expect(array_keys(session('invitation')))->toBe(['email', 'accept_url', 'expires_at']);

    $chatter = $this->owner->workspace()->chatters()->where('email', 'shared@example.com')->sole();

    expect($chatter->id)->not->toBe($foreign->id)
        ->and($this->agent->chatters()->whereKey($chatter->id)->exists())->toBeTrue()
        ->and($this->agent->chatters()->whereKey($foreign->id)->exists())->toBeFalse()
        ->and(Hash::check('foreign-pass', $foreign->fresh()->password))->toBeTrue();
});

it('invites the email of a staff account as a chatter', function (): void {
    Event::fake([AgentChatterInvited::class]);
    User::factory()->employee($this->owner)->create(['email' => 'teammate@example.com']);

    $this->actingAs($this->owner)
        ->post(route('people.chatters.invite'), [
            'email' => 'teammate@example.com',
            'agents' => [$this->agent->external_id],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $chatter = $this->owner->workspace()->chatters()->where('email', 'teammate@example.com')->sole();

    expect($this->agent->chatters()->whereKey($chatter->id)->exists())->toBeTrue();
});

it('requires at least one agent', function (): void {
    $this->actingAs($this->owner)
        ->post(route('people.chatters.invite'), [
            'email' => 'newbie@example.com',
            'agents' => [],
        ])
        ->assertSessionHasErrors('agents');

    expect($this->owner->workspace()->chatters()->where('email', 'newbie@example.com')->exists())->toBeFalse();
});

it('rejects a public agent (not invitable)', function (): void {
    $public = Agent::factory()->create(['workspace_id' => $this->owner->workspace()->id, 'is_public' => true]);

    $this->actingAs($this->owner)
        ->post(route('people.chatters.invite'), [
            'email' => 'newbie@example.com',
            'agents' => [$public->external_id],
        ])
        ->assertSessionHasErrors('agents');
});

it('rejects an agent from another workspace', function (): void {
    $otherOwner = User::factory()->create();
    $foreignAgent = Agent::factory()->create(['workspace_id' => $otherOwner->workspace()->id, 'is_public' => false]);

    $this->actingAs($this->owner)
        ->post(route('people.chatters.invite'), [
            'email' => 'newbie@example.com',
            'agents' => [$foreignAgent->external_id],
        ])
        ->assertSessionHasErrors('agents');

    expect(Chatter::query()->where('workspace_id', $otherOwner->workspace()->id)->where('email', 'newbie@example.com')->exists())->toBeFalse();
});
