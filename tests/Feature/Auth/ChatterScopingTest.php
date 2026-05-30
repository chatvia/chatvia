<?php

use App\Auth\Models\User;
use App\Teams\Models\Team;
use App\Teams\Services\TeamService;
use Domain\Auth\Actions\CreateChatterAction;
use Support\Enums\UserSource;
use Support\Enums\UserType;

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->agent = createTemplateAgent($this->owner);
});

it('flags API-created users as chatters', function (): void {
    $user = app(CreateChatterAction::class)->execute($this->owner, $this->agent, 'Jane');

    expect($user->type)->toBe(UserType::Chatter)
        ->and($user->source)->toBe(UserSource::Api)
        ->and($user->isChatter())->toBeTrue()
        ->and($user->workspace_id)->toBe($this->owner->id);
});

it('does not count chatters toward the team member quota', function (): void {
    $before = $this->owner->teamMembersCount();

    app(CreateChatterAction::class)->execute($this->owner, $this->agent, 'Jane');
    app(CreateChatterAction::class)->execute($this->owner, $this->agent, 'John');

    expect($this->owner->fresh()->teamMembersCount())->toBe($before);
});

it('does not create a team when a chatter is created', function (): void {
    $teamsBefore = Team::count();

    app(CreateChatterAction::class)->execute($this->owner, $this->agent, 'Jane');

    expect(Team::count())->toBe($teamsBefore);
});

it('excludes chatters from the team member list', function (): void {
    $user = app(CreateChatterAction::class)->execute($this->owner, $this->agent, 'Jane');

    expect($this->owner->primaryTeam()->members()->where('users.id', $user->id)->exists())->toBeFalse();
});

it('attaches the agent grant on creation so canAccessAgent passes', function (): void {
    $user = app(CreateChatterAction::class)->execute($this->owner, $this->agent, 'Jane');

    expect($user->canAccessAgent($this->agent))->toBeTrue();
});

it('refuses canAccessAgent for an agent the chatter was not granted', function (): void {
    $other = createTemplateAgent($this->owner);

    $user = app(CreateChatterAction::class)->execute($this->owner, $this->agent, 'Jane');

    expect($user->canAccessAgent($other))->toBeFalse();
});

describe('dashboard access', function (): void {
    it('lets the owner access the dashboard', function (): void {
        expect($this->owner->canAccessDashboard())->toBeTrue();
    });

    it('blocks a chatter', function (): void {
        $user = app(CreateChatterAction::class)->execute($this->owner, $this->agent, 'Jane');

        expect($user->canAccessDashboard())->toBeFalse();
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
