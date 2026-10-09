<?php

use App\Identity\Workspace\Models\User;
use Database\Factories\AgentFileFactory;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Membership\Team\Actions\Roles\CreateTeamRoleAction;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Str;

/**
 * Knowledge source routes: the per-agent permissions moved from the
 * controllers onto the routes, a file can only be deleted through an agent
 * that uses it, and the shared pools list the workspace's sources to
 * teammates too (sources belong to the workspace, not to who uploaded them).
 */
function sourceRoutesTeammate(User $owner, array $permissions): User
{
    $team = $owner->primaryTeam();

    $role = app(CreateTeamRoleAction::class)->execute(new SaveTeamRoleData(
        team: $team,
        name: 'Role-'.Str::random(8),
        permissions: $permissions,
        allAgents: true,
        agentExternalIds: [],
    ));

    $member = User::factory()->employee($owner)->create(['current_team_id' => $team->id]);
    $team->members()->attach($member->id, ['role_id' => $role->id]);

    return $member;
}

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->owner->billing()->firstOrCreate([]);
    $this->owner->billing->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_active',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test',
        'quantity' => 1,
    ]);
    $this->owner->refresh();

    $this->agent = createAgent($this->owner);
});

it('gates the source pages by their training permission', function (string $permission, string $route): void {
    $blocked = sourceRoutesTeammate($this->owner, []);
    $allowed = sourceRoutesTeammate($this->owner, [$permission]);

    $this->actingAs($blocked)->get(route($route, $this->agent))->assertForbidden();
    $this->actingAs($allowed)->get(route($route, $this->agent))->assertOk();
})->with([
    'files' => ['agent.sources.train.files', 'agents.sources.files'],
    'files data' => ['agent.sources.train.files', 'agents.sources.files.data'],
    'audio' => ['agent.sources.train.audio', 'agents.sources.audio'],
    'video' => ['agent.sources.train.video', 'agents.sources.video'],
    'web' => ['agent.sources.train.web', 'agents.sources.web'],
]);

it('gates file uploads by agent.sources.train.files', function (): void {
    $blocked = sourceRoutesTeammate($this->owner, []);

    $this->actingAs($blocked)
        ->postJson(route('agents.sources.files.upload', $this->agent))
        ->assertForbidden();
});

it('does not delete a file the agent does not use', function (): void {
    $file = AgentFileFactory::new()->create(['user_id' => $this->owner->id]);

    $this->actingAs($this->owner)
        ->deleteJson(route('agents.sources.files.delete', [$this->agent, $file->id]))
        ->assertNotFound();

    expect(SourceFile::query()->find($file->id))->not->toBeNull();
});

it('lists the workspace pools to a teammate', function (): void {
    $file = AgentFileFactory::new()->create(['user_id' => $this->owner->id, 'status' => 'ingested', 'filetype' => 'pdf']);
    $website = SourceWebsite::query()->create(['user_id' => $this->owner->id, 'url' => 'https://docs.example.test', 'status' => 'ingested']);
    $teammate = sourceRoutesTeammate($this->owner, []);

    $this->actingAs($teammate)
        ->getJson(route('agents.sources.list', [$this->agent, 'type' => 'pdf']))
        ->assertOk()
        ->assertJsonPath('0.id', $file->id);

    $this->actingAs($teammate)
        ->getJson(route('agents.sources.trained-websites.index', $this->agent))
        ->assertOk()
        ->assertJsonPath('0.id', $website->id);
});

it('unlinks a shared file from the agent and refuses its last link', function (): void {
    $other = createAgent($this->owner);
    $shared = AgentFileFactory::new()->create(['user_id' => $this->owner->id]);
    $this->agent->files()->attach($shared->id);
    $other->files()->attach($shared->id);
    $onlyHere = AgentFileFactory::new()->create(['user_id' => $this->owner->id]);
    $this->agent->files()->attach($onlyHere->id);

    $this->actingAs($this->owner)
        ->postJson(route('agents.sources.files.unlink', [$this->agent, $shared->id]))
        ->assertOk();

    $this->actingAs($this->owner)
        ->postJson(route('agents.sources.files.unlink', [$this->agent, $onlyHere->id]))
        ->assertStatus(400);

    expect($this->agent->files()->whereKey($shared->id)->exists())->toBeFalse()
        ->and($other->files()->whereKey($shared->id)->exists())->toBeTrue()
        ->and($this->agent->files()->whereKey($onlyHere->id)->exists())->toBeTrue();
});
