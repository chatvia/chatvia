<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Agent\Prompt\Models\AgentPrompt;
use Domain\Membership\Team\Actions\Roles\CreateTeamRoleAction;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    // The settings controllers gate every action on has_team_permission(),
    // which requires the user to have a current/primary team.
    app(TeamService::class)->createDefaultTeam($this->user);
});

function makePrompt(User $user, array $overrides = []): AgentPrompt
{
    return $user->prompts()->create(array_merge([
        'name' => 'Support persona',
        'prompt' => 'You are a helpful support agent.',
    ], $overrides));
}

it('creates a prompt', function (): void {
    $this->actingAs($this->user)
        ->post(route('library.prompts.store'), [
            'name' => 'Sales persona',
            'prompt' => 'You are a persuasive sales agent.',
        ])
        ->assertRedirect();

    expect($this->user->prompts()->where('name', 'Sales persona')->exists())->toBeTrue();
});

it('validates required fields on create', function (): void {
    $this->actingAs($this->user)
        ->post(route('library.prompts.store'), ['name' => '', 'prompt' => ''])
        ->assertSessionHasErrors(['name', 'prompt']);
});

it('rejects a duplicate name on create', function (): void {
    makePrompt($this->user, ['name' => 'Taken']);

    $this->actingAs($this->user)
        ->post(route('library.prompts.store'), ['name' => 'Taken', 'prompt' => 'x'])
        ->assertSessionHasErrors('name');
});

it('updates a prompt', function (): void {
    $prompt = makePrompt($this->user);

    $this->actingAs($this->user)
        ->patch(route('library.prompts.update', ['external_id' => $prompt->external_id]), [
            'name' => 'Renamed',
            'prompt' => 'New body',
        ])
        ->assertRedirect();

    $fresh = $prompt->fresh();
    expect($fresh->name)->toBe('Renamed');
    expect($fresh->prompt)->toBe('New body');
});

it('cannot update a prompt owned by another user', function (): void {
    $other = User::factory()->create();
    $prompt = makePrompt($other);

    $this->actingAs($this->user)
        ->patch(route('library.prompts.update', ['external_id' => $prompt->external_id]), [
            'name' => 'Hijack',
            'prompt' => 'x',
        ])
        ->assertForbidden();
});

it('deletes a prompt and detaches it from agents', function (): void {
    $prompt = makePrompt($this->user);

    $agent = Agent::create([
        'user_id' => $this->user->id,
        'team_id' => $this->user->primaryTeam()->id,
        'name' => 'Bot',
        'external_id' => Str::random(14),
        'sub_domain' => Str::random(14),
        'agent_prompt_id' => $prompt->id,
        'is_public' => false,
        'source' => 'dashboard',
    ]);

    $this->actingAs($this->user)
        ->delete(route('library.prompts.destroy', ['external_id' => $prompt->external_id]))
        ->assertRedirect();

    expect(AgentPrompt::whereKey($prompt->id)->exists())->toBeFalse();
    expect($agent->fresh()->agent_prompt_id)->toBeNull();
});

/**
 * A teammate of $owner on the owner's primary team, under a role granting
 * exactly $permissions.
 *
 * @param  list<string>  $permissions
 */
function promptsTeammate(User $owner, array $permissions): User
{
    $team = $owner->primaryTeam();

    $role = app(CreateTeamRoleAction::class)->execute(new SaveTeamRoleData(
        team: $team,
        name: 'Role-'.Str::random(10),
        permissions: $permissions,
        allAgents: true,
        agentExternalIds: [],
    ));

    $member = User::factory()->employee($owner)->create(['current_team_id' => $team->id]);
    $team->members()->attach($member->id, ['role_id' => $role->id]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $member;
}

describe('teammates share the workspace prompt library', function (): void {
    beforeEach(function (): void {
        $this->teammate = promptsTeammate($this->user, ['account.prompts']);
    });

    it('stores a teammate-created prompt under the workspace and lists it back to them', function (): void {
        $this->actingAs($this->teammate)
            ->post(route('library.prompts.store'), ['name' => 'Team persona', 'prompt' => 'Be kind.'])
            ->assertRedirect();

        $prompt = AgentPrompt::query()->where('name', 'Team persona')->sole();
        expect($prompt->user_id)->toBe($this->user->id);

        $this->actingAs($this->teammate)
            ->getJson(route('library.prompts.list'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $prompt->external_id);
    });

    it('lets a teammate edit and delete a workspace prompt', function (): void {
        $prompt = makePrompt($this->user);

        $this->actingAs($this->teammate)
            ->patch(route('library.prompts.update', ['external_id' => $prompt->external_id]), [
                'name' => 'Edited by teammate',
                'prompt' => 'New body',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect($prompt->fresh()->name)->toBe('Edited by teammate');

        $this->actingAs($this->teammate)
            ->delete(route('library.prompts.destroy', ['external_id' => $prompt->external_id]))
            ->assertRedirect();

        expect(AgentPrompt::whereKey($prompt->id)->exists())->toBeFalse();
    });

    it('rejects a teammate rename that collides with another workspace prompt', function (): void {
        makePrompt($this->user, ['name' => 'Taken']);
        $prompt = makePrompt($this->user, ['name' => 'Mine']);

        $this->actingAs($this->teammate)
            ->patch(route('library.prompts.update', ['external_id' => $prompt->external_id]), [
                'name' => 'Taken',
                'prompt' => 'x',
            ])
            ->assertSessionHasErrors('name');
    });

    it('still hides another workspace\'s prompts from a teammate', function (): void {
        $other = User::factory()->create();
        $foreign = makePrompt($other);

        $this->actingAs($this->teammate)
            ->delete(route('library.prompts.destroy', ['external_id' => $foreign->external_id]))
            ->assertRedirect();

        expect(AgentPrompt::whereKey($foreign->id)->exists())->toBeTrue();
    });
});
