<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Services\TeamService;

/**
 * An agent's Chatters page: granting an existing chatter of the workspace
 * access to the agent, and revoking it (the chatter account stays). Every
 * route of the page needs agent.chatters.manage.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->owner->refresh();
    $this->agent = createAgent($this->owner, ['is_public' => false]);
    $this->chatter = Chatter::factory()->ofWorkspace($this->owner)->create();
});

it('grants an existing chatter of the workspace access to the agent', function (): void {
    $this->actingAs($this->owner)
        ->from(route('agents.chatters.index', $this->agent))
        ->post(route('agents.chatters.grant', [$this->agent, $this->chatter->external_id]))
        ->assertRedirect(route('agents.chatters.index', $this->agent));

    expect($this->chatter->fresh()->canAccessAgent($this->agent))->toBeTrue();
});

it('grants nothing on a public agent, which has no per-chatter access', function (): void {
    $this->agent->update(['is_public' => true]);

    $this->actingAs($this->owner)
        ->postJson(route('agents.chatters.grant', [$this->agent, $this->chatter->external_id]))
        ->assertUnprocessable();

    expect($this->agent->chatters()->whereKey($this->chatter->id)->exists())->toBeFalse();
});

it('404s a chatter of another workspace on grant', function (): void {
    $stranger = Chatter::factory()->ofWorkspace(User::factory()->create())->create();

    $this->actingAs($this->owner)
        ->postJson(route('agents.chatters.grant', [$this->agent, $stranger->external_id]))
        ->assertNotFound();

    expect($this->agent->chatters()->whereKey($stranger->id)->exists())->toBeFalse();
});

it('revokes the agent from a chatter and keeps the chatter and its other agents', function (): void {
    $other = createAgent($this->owner, ['is_public' => false]);
    $this->chatter->agents()->attach([
        $this->agent->id => ['workspace_id' => $this->agent->workspace_id],
        $other->id => ['workspace_id' => $other->workspace_id],
    ]);

    $this->actingAs($this->owner)
        ->delete(route('agents.chatters.destroy', [$this->agent, $this->chatter->external_id]))
        ->assertRedirect();

    $chatter = $this->chatter->fresh();

    expect($chatter)->not->toBeNull()
        ->and($chatter->canAccessAgent($this->agent))->toBeFalse()
        ->and($chatter->canAccessAgent($other))->toBeTrue();
});

it('404s revoking a chatter the agent was never granted to', function (): void {
    $this->actingAs($this->owner)
        ->deleteJson(route('agents.chatters.destroy', [$this->agent, $this->chatter->external_id]))
        ->assertNotFound();
});

describe('agent.chatters.manage', function (): void {
    it('keeps a teammate without it off every route of the page', function (): void {
        $this->chatter->agents()->attach($this->agent->id, ['workspace_id' => $this->agent->workspace_id]);
        $granted = Chatter::factory()->ofWorkspace($this->owner)->create();
        $teammate = teammateWithPermissions($this->owner, ['agent.setup', 'agent.chat.view']);

        $this->actingAs($teammate)->getJson(route('agents.chatters.index', $this->agent))->assertForbidden();
        $this->actingAs($teammate)->postJson(route('agents.chatters.store', $this->agent), ['email' => 'new-chatter@example.test'])->assertForbidden();
        $this->actingAs($teammate)->postJson(route('agents.chatters.grant', [$this->agent, $granted->external_id]))->assertForbidden();
        $this->actingAs($teammate)->deleteJson(route('agents.chatters.destroy', [$this->agent, $this->chatter->external_id]))->assertForbidden();

        expect($granted->fresh()->canAccessAgent($this->agent))->toBeFalse()
            ->and($this->chatter->fresh()->canAccessAgent($this->agent))->toBeTrue()
            ->and(Chatter::query()->where('email', 'new-chatter@example.test')->exists())->toBeFalse();
    });

    it('lets a teammate with it list, grant and revoke', function (): void {
        $teammate = teammateWithPermissions($this->owner, ['agent.chatters.manage']);

        $this->actingAs($teammate)
            ->getJson(route('agents.chatters.index', $this->agent))
            ->assertOk()
            ->assertJsonPath('data.0.has_access', false);

        $this->actingAs($teammate)->post(route('agents.chatters.grant', [$this->agent, $this->chatter->external_id]))->assertRedirect();
        expect($this->chatter->fresh()->canAccessAgent($this->agent))->toBeTrue();

        $this->actingAs($teammate)->delete(route('agents.chatters.destroy', [$this->agent, $this->chatter->external_id]))->assertRedirect();
        expect($this->chatter->fresh()->canAccessAgent($this->agent))->toBeFalse();
    });
});
