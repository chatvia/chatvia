<?php

/**
 * DeleteUserAccountAction: tearing down a workspace removes the workspace row,
 * its owner, teammates, chatters (by FK cascade), message limits, leads,
 * tokens, sessions, settings rows and chat memory, and leaves every other
 * workspace alone. The storage wipe and the batching have their own files
 * (DeleteUserAccountStorageWipeTest, DeleteUserAccountBatchingTest).
 */

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Actions\Account\DeleteUserAccountAction;
use App\Identity\Workspace\Models\MessageLimit;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;
use Domain\Agent\Models\Agent;
use Domain\Agent\Models\ModelSetting;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session as ChatSession;
use Domain\Conversation\Services\Memory\ChatCache;
use Domain\Form\Models\FormSubmission;
use Domain\Membership\Team\Models\Team;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    // The teardown wipes workspaces/{external_id}, users/{external_id} and
    // chatters/{external_id} on the default, assets and local disks.
    Storage::fake();
    Storage::fake('assets');
    Storage::fake('local');
});

it('hard-deletes the user row and its workspace', function (): void {
    $user = User::factory()->create();
    $workspaceId = $user->workspace()->id;

    app(DeleteUserAccountAction::class)->execute($user);

    expect(User::whereKey($user->id)->exists())->toBeFalse()
        ->and(Workspace::whereKey($workspaceId)->exists())->toBeFalse();
});

it('tears down team memberships, owned teams, and workspace sub-users', function (): void {
    $owner = User::factory()->create();
    // A factory owner has no team yet, so create the default one.
    $team = app(TeamService::class)->createDefaultTeam($owner);
    $role = $team->roles()->firstOrFail();

    // The owner is a member of their own team — this team_user row is exactly
    // what the team_user.team_id RESTRICT FK trips on if not cleared first.
    $team->members()->attach($owner->id, ['role_id' => $role->id]);

    $employee = User::factory()->employee($owner)->create();
    $team->members()->attach($employee->id, ['role_id' => $role->id]);

    $chatter = Chatter::factory()->ofWorkspace($owner)->create();

    app(DeleteUserAccountAction::class)->execute($owner);

    expect(User::whereKey($owner->id)->exists())->toBeFalse()
        ->and(User::whereKey($employee->id)->exists())->toBeFalse()
        ->and(Chatter::whereKey($chatter->id)->exists())->toBeFalse()
        ->and(Team::whereKey($team->id)->exists())->toBeFalse()
        ->and(DB::table('team_user')->where('team_id', $team->id)->exists())->toBeFalse();
});

it('deletes the workspace message limit and its chatters\' limits', function (): void {
    $owner = User::factory()->create();
    $workspace = $owner->workspace();
    $chatter = Chatter::factory()->ofWorkspace($owner)->create();

    MessageLimit::factory()->forWorkspace($workspace)->create();
    MessageLimit::factory()->forChatter($chatter)->create();

    app(DeleteUserAccountAction::class)->execute($owner);

    expect(MessageLimit::query()->where('workspace_id', $workspace->id)->exists())->toBeFalse()
        ->and(MessageLimit::query()->where('chatter_id', $chatter->id)->exists())->toBeFalse();
});

it('deletes the workspace chatters with their grants and chat sessions', function (): void {
    $owner = User::factory()->create();
    $agent = Agent::factory()->create(['workspace_id' => $owner->workspace()->id]);
    $chatter = Chatter::factory()->grantedTo($agent)->create();
    $session = ChatSession::factory()->forChatter($chatter)->create(['agent_id' => $agent->id]);

    app(DeleteUserAccountAction::class)->execute($owner);

    expect(Chatter::whereKey($chatter->id)->exists())->toBeFalse()
        ->and(DB::table('agent_chatter')->where('chatter_id', $chatter->id)->exists())->toBeFalse()
        ->and(ChatSession::whereKey($session->id)->exists())->toBeFalse();
});

it('leaves other workspaces untouched while tearing one down', function (): void {
    $owner = User::factory()->create();
    $otherOwner = User::factory()->create();
    $otherEmployee = User::factory()->employee($otherOwner)->create();

    // The same email is a chatter in both workspaces: only this one goes.
    $chatter = Chatter::factory()->ofWorkspace($owner)->create(['email' => 'both@example.test']);
    $otherChatter = Chatter::factory()->ofWorkspace($otherOwner)->create(['email' => 'both@example.test']);
    MessageLimit::factory()->forChatter($otherChatter)->create();

    app(DeleteUserAccountAction::class)->execute($owner);

    expect(User::whereKey($otherOwner->id)->exists())->toBeTrue()
        ->and(User::whereKey($otherEmployee->id)->exists())->toBeTrue()
        ->and(Chatter::whereKey($chatter->id)->exists())->toBeFalse()
        ->and(Chatter::whereKey($otherChatter->id)->exists())->toBeTrue()
        ->and(MessageLimit::query()->where('chatter_id', $otherChatter->id)->exists())->toBeTrue();
});

it('flushes the deleted workspace chat memory cache and leaves other workspaces intact', function (): void {
    // Every chat-memory entry carries ChatCache::workspaceTag(), so a single
    // tag flush must clear the deleted workspace and nothing else.

    $agent = Agent::factory()->create();
    $owner = $agent->workspace->owner;
    $workspaceTag = ChatCache::workspaceTag($agent->workspace->external_id);

    $survivor = User::factory()->create();
    $survivorTag = ChatCache::workspaceTag($survivor->workspace()->external_id);

    Cache::driver('chat')->tags([$workspaceTag, 'session-a'])->forever('session-a', ['conversations' => []]);
    Cache::driver('chat')->tags([$survivorTag, 'session-b'])->forever('session-b', ['conversations' => []]);

    app(DeleteUserAccountAction::class)->execute($owner);

    expect(Cache::driver('chat')->tags([$workspaceTag, 'session-a'])->has('session-a'))->toBeFalse()
        ->and(Cache::driver('chat')->tags([$survivorTag, 'session-b'])->has('session-b'))->toBeTrue();
});

it('deletes leads, Sanctum tokens and HTTP sessions the FK cascade would orphan', function (): void {
    $owner = User::factory()->create();
    $employee = User::factory()->employee($owner)->create();
    $agent = Agent::factory()->create(['workspace_id' => $owner->workspace()->id]);

    $lead = FormSubmission::factory()->forAgent($agent)->create();

    $owner->createToken('owner-cli');
    $employee->createToken('emp-cli');

    foreach ([$owner->id, $employee->id] as $i => $uid) {
        DB::table('sessions')->insert([
            'id' => "sess-ws-{$i}",
            'user_id' => $uid,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'x',
            'last_activity' => now()->timestamp,
        ]);
    }

    app(DeleteUserAccountAction::class)->execute($owner);

    expect(FormSubmission::whereKey($lead->id)->exists())->toBeFalse()
        ->and(DB::table('personal_access_tokens')->whereIn('tokenable_id', [$owner->id, $employee->id])->where('tokenable_type', $owner->getMorphClass())->exists())->toBeFalse()
        ->and(DB::table('sessions')->whereIn('user_id', [$owner->id, $employee->id])->exists())->toBeFalse();
});

it('deletes the settings rows of its agents, deleted ones included, and of its members', function (): void {
    $agent = Agent::factory()->create();
    $owner = $agent->workspace->owner;
    $trashed = Agent::factory()->create(['workspace_id' => $agent->workspace_id]);
    $trashed->delete();
    $otherAgent = Agent::factory()->create();

    $setting = fn (Agent|User $model): ModelSetting => ModelSetting::create([
        'model_type' => $model->getMorphClass(),
        'model_id' => $model->id,
        'group' => 'appearance',
        'key' => 'title',
        'value' => 'Hi',
        'data_type' => 'string',
    ]);

    $gone = [$setting($agent)->id, $setting($trashed)->id, $setting($owner)->id];
    $kept = $setting($otherAgent)->id;

    app(DeleteUserAccountAction::class)->execute($owner);

    expect(ModelSetting::query()->whereKey($gone)->exists())->toBeFalse()
        ->and(ModelSetting::find($kept))->not->toBeNull();
});
