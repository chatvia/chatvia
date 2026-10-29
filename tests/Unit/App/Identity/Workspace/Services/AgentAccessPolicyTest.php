<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\AgentAccessPolicy;
use Domain\Membership\Team\Actions\Team\CreateDefaultTeamAction;
use Illuminate\Support\Facades\Gate;

/**
 * The one agent-access rule for staff accounts: the `access-agent` Gate
 * (dashboard), User::canAccessAgent() (hosted chat) and the chat switcher's
 * list (User::listAccessibleAgents()) all ask AgentAccessPolicy, so they
 * always agree. One case per staff account type, plus the cross-tenant case.
 * Chatters are not users: Chatter::canAccessAgent() checks their own grants
 * (ChatterTest).
 */
beforeEach(function (): void {
    $this->owner = User::factory()->workspace()->create();
    $this->team = app(CreateDefaultTeamAction::class)->execute($this->owner);
    $this->owner->update(['current_team_id' => $this->team->id]);
    $this->agent = makeTeamAgent($this->owner, 'Support', $this->team);
    $this->policy = app(AgentAccessPolicy::class);
});

it('lets in the owner, a teammate whose role reaches the agent (first team when none is picked) and platform staff on their own agents', function (): void {
    $teammate = teammateWithPermissions($this->owner, [], $this->team);
    $teamless = teammateWithPermissions($this->owner, [], $this->team);
    $teamless->update(['current_team_id' => null]);
    $teamless = $teamless->fresh();
    $admin = User::factory()->admin()->create();
    $adminAgent = createAgent($admin);

    expect($this->policy->canManage($this->owner, $this->agent))->toBeTrue()
        ->and($this->policy->canChat($this->owner, $this->agent))->toBeTrue()
        ->and(Gate::forUser($this->owner)->allows('access-agent', $this->agent))->toBeTrue()
        ->and($this->owner->canAccessAgent($this->agent))->toBeTrue()
        ->and($this->policy->canManage($teammate, $this->agent))->toBeTrue()
        ->and($this->policy->canChat($teammate, $this->agent))->toBeTrue()
        ->and($this->policy->canManage($teamless, $this->agent))->toBeTrue()
        ->and($teamless->canAccessAgent($this->agent))->toBeTrue()
        ->and($this->policy->canManage($admin, $adminAgent))->toBeTrue()
        ->and($this->policy->canChat($admin, $adminAgent))->toBeTrue();
});

it('keeps out another workspace\'s owner, platform staff, a teammate whose role misses the agent and a staff id a chatter grant shares', function (): void {
    $intruder = User::factory()->workspace()->create();
    $intruderTeam = app(CreateDefaultTeamAction::class)->execute($intruder);
    $intruder->update(['current_team_id' => $intruderTeam->id]);
    $intruder = $intruder->fresh();
    $admin = User::factory()->admin()->create();
    $scopedOut = teammateWithPermissions($this->owner, [], $this->team, allAgents: false);
    Chatter::factory()->grantedTo($this->agent)->create(['id' => $scopedOut->id]);

    foreach ([$intruder, $admin, $scopedOut] as $outsider) {
        expect($this->policy->canManage($outsider, $this->agent))->toBeFalse()
            ->and($this->policy->canChat($outsider, $this->agent))->toBeFalse()
            ->and($outsider->canAccessAgent($this->agent))->toBeFalse()
            ->and(Gate::forUser($outsider)->allows('access-agent', $this->agent))->toBeFalse();
    }
    expect($this->policy->chattableAgents($scopedOut)->pluck('id')->all())->not->toContain($this->agent->id);
});

it('lists every workspace agent for the owner, a teammate\'s current or first team\'s, a scoped role\'s named ones, and none without a team', function (): void {
    $other = makeTeamAgent($this->owner, 'B', $this->team);
    $allowed = makeTeamAgent($this->owner, 'Allowed', $this->team);
    $secondTeam = app(CreateDefaultTeamAction::class)->execute($this->owner);
    $elsewhere = makeTeamAgent($this->owner, 'Elsewhere', $secondTeam);
    $teammate = teammateWithPermissions($this->owner, [], $this->team);
    $teamless = teammateWithPermissions($this->owner, [], $this->team);
    $teamless->update(['current_team_id' => null]);
    $scoped = teammateWithPermissions($this->owner, [], $this->team, allAgents: false, agentExternalIds: [$allowed->external_id]);
    $noTeam = User::factory()->employee($this->owner)->create(['current_team_id' => null]);
    $ids = fn (User $user): array => $this->policy->chattableAgents($user)->pluck('id')->sort()->values()->all();
    $teamAgents = collect([$this->agent->id, $other->id, $allowed->id])->sort()->values()->all();

    expect($ids($this->owner))->toBe(collect([...$teamAgents, $elsewhere->id])->sort()->values()->all())
        ->and($ids($teammate))->toBe($teamAgents)
        ->and($ids($teamless->fresh()))->toBe($teamAgents)
        ->and($ids($scoped))->toBe([$allowed->id])
        ->and($ids($noTeam))->toBe([]);
});
