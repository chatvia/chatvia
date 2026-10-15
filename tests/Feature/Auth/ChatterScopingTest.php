<?php

use App\Identity\Chatter\Actions\CreateChatterAction;
use App\Identity\Chatter\Data\CreateChatterData;
use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Models\Team;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Chatters (a workspace's end-users) live in their own table, apart from the
 * staff accounts: they never become teammates or team members, reach only the
 * agents granted to them inside their own workspace, and cannot open the
 * dashboard.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->agent = createAgent($this->owner);
});

it('keeps chatters out of the teammates', function (): void {
    $workspace = $this->owner->workspace();
    $teammatesBefore = $workspace->teammates()->count();
    $staffBefore = $workspace->members()->count();

    app(CreateChatterAction::class)->execute(new CreateChatterData($this->owner->workspace(), $this->agent, 'Jane'));
    app(CreateChatterAction::class)->execute(new CreateChatterData($this->owner->workspace(), $this->agent, 'John'));

    expect($workspace->teammates()->count())->toBe($teammatesBefore)
        ->and($workspace->members()->count())->toBe($staffBefore)
        ->and($workspace->chatters()->count())->toBe(2);
});

it('does not create a team when a chatter is created', function (): void {
    $teamsBefore = Team::count();

    app(CreateChatterAction::class)->execute(new CreateChatterData($this->owner->workspace(), $this->agent, 'Jane'));

    expect(Team::count())->toBe($teamsBefore);
});

it('does not add a chatter to the team member list', function (): void {
    // Compared by count: a chatter's id comes from another table and can
    // equal a team member's id.
    $membersBefore = $this->owner->primaryTeam()->members()->count();

    app(CreateChatterAction::class)->execute(new CreateChatterData($this->owner->workspace(), $this->agent, 'Jane'));

    expect($this->owner->primaryTeam()->members()->count())->toBe($membersBefore);
});

it('refuses canAccessAgent for an agent the chatter was not granted', function (): void {
    $other = createAgent($this->owner);

    $chatter = app(CreateChatterAction::class)->execute(new CreateChatterData($this->owner->workspace(), $this->agent, 'Jane'));

    expect($chatter->canAccessAgent($other))->toBeFalse();
});

it('refuses canAccessAgent for another workspace\'s agent, which the database will not even grant', function (): void {
    $foreignAgent = createAgent(User::factory()->create());

    $chatter = app(CreateChatterAction::class)->execute(new CreateChatterData($this->owner->workspace(), $this->agent, 'Jane'));

    expect(fn () => DB::transaction(fn () => $chatter->agents()->attach($foreignAgent->id, ['workspace_id' => $chatter->workspace_id])))
        ->toThrow(QueryException::class)
        ->and($chatter->canAccessAgent($foreignAgent))->toBeFalse();
});

describe('dashboard access', function (): void {
    it('lets the owner access the dashboard', function (): void {
        expect($this->owner->canAccessDashboard())->toBeTrue();
    });

    it('allows an employee', function (): void {
        $employee = User::factory()->employee($this->owner)->create();

        expect($employee->canAccessDashboard())->toBeTrue();
    });

    it('allows an admin', function (): void {
        $admin = User::factory()->admin()->create();

        expect($admin->canAccessDashboard())->toBeTrue();
    });
});
