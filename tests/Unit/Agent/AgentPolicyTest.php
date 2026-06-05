<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Agent\Policies\AgentPolicy;

/**
 * AgentPolicy matrix. The view/update/delete/restore/forceDelete abilities
 * all delegate to `User::canAccessAgent($agent)`, so the matrix below maps
 * to the *real* contract: who can touch an agent.
 *
 *  - Admin             → always allowed
 *  - Workspace owner   → only their own agents
 *  - Chatter           → only agents on the accessible-agents pivot
 *  - Unrelated user    → denied
 */

beforeEach(function (): void {
    $this->policy = app(AgentPolicy::class);
});

it('viewAny is granted only to admins', function (): void {
    expect($this->policy->viewAny(User::factory()->admin()->create()))->toBeTrue()
        ->and($this->policy->viewAny(User::factory()->workspace()->create()))->toBeFalse();
});

it('create is granted to admins and workspace owners but not to chatters', function (): void {
    $workspace = User::factory()->workspace()->create();
    $admin = User::factory()->admin()->create();
    $chatter = User::factory()->chatter($workspace)->create();

    expect($this->policy->create($admin))->toBeTrue()
        ->and($this->policy->create($workspace))->toBeTrue()
        ->and($this->policy->create($chatter))->toBeFalse();
});

it('view/update/delete grant the workspace owner access to their own agents', function (): void {
    $agent = Agent::factory()->create();
    $owner = $agent->user;

    expect($this->policy->view($owner, $agent))->toBeTrue()
        ->and($this->policy->update($owner, $agent))->toBeTrue()
        ->and($this->policy->delete($owner, $agent))->toBeTrue()
        ->and($this->policy->restore($owner, $agent))->toBeTrue()
        ->and($this->policy->forceDelete($owner, $agent))->toBeTrue();
});

it('view/update/delete deny an unrelated workspace owner', function (): void {
    $agent = Agent::factory()->create();
    $intruder = Agent::factory()->create()->user; // different workspace

    expect($this->policy->view($intruder, $agent))->toBeFalse()
        ->and($this->policy->update($intruder, $agent))->toBeFalse()
        ->and($this->policy->delete($intruder, $agent))->toBeFalse();
});

it('regression guard: AgentPolicy and the access-agent Gate disagree about admin escape hatch', function (): void {
    // NOTE: this test documents a real divergence in the codebase. The
    // `Gate::define('access-agent', ...)` in AppServiceProvider has an
    // `if ($user->isAdmin()) return true` early return — admins get access
    // to ANY agent. AgentPolicy::view delegates to User::canAccessAgent()
    // which has NO admin special-case; without a currentTeam, an admin is
    // denied. Two authz paths, two answers. Worth flagging for the next
    // sweep — either align canAccessAgent to grant admins, or document
    // that the Gate is the canonical entry point for admin access.
    $agent = Agent::factory()->create();
    $admin = User::factory()->admin()->create();

    // The policy denies, demonstrating the divergence:
    expect($this->policy->view($admin, $agent))->toBeFalse();

    // The Gate, however, allows:
    expect(Illuminate\Support\Facades\Gate::forUser($admin)->allows('access-agent', $agent))->toBeTrue();
});

it('view denies a chatter that has not been granted access to the agent on the pivot', function (): void {
    $agent = Agent::factory()->create();
    $chatter = User::factory()->chatter($agent->user)->create();

    // Default chatter has no `agent_user` pivot row → no access.
    expect($this->policy->view($chatter, $agent))->toBeFalse()
        ->and($this->policy->update($chatter, $agent))->toBeFalse();
});
