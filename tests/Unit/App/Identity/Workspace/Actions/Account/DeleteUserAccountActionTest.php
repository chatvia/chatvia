<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Actions\Account\DeleteUserAccountAction;
use App\Identity\Workspace\Models\MessageLimit;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;
use Domain\Agent\Models\Agent;
use Domain\Agent\Models\ModelSetting;
use Domain\Conversation\Models\Session as ChatSession;
use Domain\Conversation\Services\Memory\ChatCache;
use Domain\Form\Models\FormSubmission;
use Domain\Membership\Team\Actions\Team\CreateDefaultTeamAction;
use Domain\Membership\Team\Models\Team;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * DeleteUserAccountAction: tearing down a workspace removes the workspace row,
 * its owner, teammates, chatters (by FK cascade), message limits, leads,
 * tokens, sessions, settings rows and chat memory, and leaves every other
 * workspace alone. The storage wipe and the batching have their own files
 * (DeleteUserAccountStorageWipeTest, DeleteUserAccountBatchingTest).
 */
beforeEach(function (): void {
    Storage::fake();
    Storage::fake('assets');
    Storage::fake('local');
});

/** A settings row of the agent or member. */
function duaSetting(Agent|User $model): ModelSetting
{
    return ModelSetting::create([
        'model_type' => $model->getMorphClass(),
        'model_id' => $model->id,
        'group' => 'appearance',
        'key' => 'title',
        'value' => 'Hi',
        'data_type' => 'string',
    ]);
}

it('deletes the workspace with its owner, teammates, teams, chatters, grants, chat sessions and message limits', function (): void {
    $owner = User::factory()->create();
    $workspace = $owner->workspace();
    $team = app(CreateDefaultTeamAction::class)->execute($owner);
    $role = $team->roles()->firstOrFail();
    $team->members()->attach($owner->id, ['role_id' => $role->id]);
    $employee = User::factory()->employee($owner)->create();
    $team->members()->attach($employee->id, ['role_id' => $role->id]);
    $agent = Agent::factory()->create(['workspace_id' => $workspace->id]);
    $chatter = Chatter::factory()->grantedTo($agent)->create();
    $session = ChatSession::factory()->forChatter($chatter)->create(['agent_id' => $agent->id]);
    MessageLimit::factory()->forWorkspace($workspace)->create();
    MessageLimit::factory()->forChatter($chatter)->create();

    app(DeleteUserAccountAction::class)->execute($owner);

    expect(User::query()->whereKey($owner->id)->exists())->toBeFalse()
        ->and(Workspace::query()->whereKey($workspace->id)->exists())->toBeFalse()
        ->and(User::query()->whereKey($employee->id)->exists())->toBeFalse()
        ->and(Team::query()->whereKey($team->id)->exists())->toBeFalse()
        ->and(DB::table('team_user')->where('team_id', $team->id)->exists())->toBeFalse()
        ->and(Chatter::query()->whereKey($chatter->id)->exists())->toBeFalse()
        ->and(DB::table('agent_chatter')->where('chatter_id', $chatter->id)->exists())->toBeFalse()
        ->and(ChatSession::query()->whereKey($session->id)->exists())->toBeFalse()
        ->and(MessageLimit::query()->where('workspace_id', $workspace->id)->exists())->toBeFalse()
        ->and(MessageLimit::query()->where('chatter_id', $chatter->id)->exists())->toBeFalse();
});

it('deletes the leads, tokens, HTTP sessions, settings rows and chat memory the FK cascade would orphan', function (): void {
    $agent = Agent::factory()->create();
    $owner = $agent->workspace->owner;
    $employee = User::factory()->employee($owner)->create();
    $trashed = Agent::factory()->create(['workspace_id' => $agent->workspace_id]);
    $trashed->delete();
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
    $settings = [duaSetting($agent)->id, duaSetting($trashed)->id, duaSetting($owner)->id];
    $workspaceTag = ChatCache::workspaceTag($agent->workspace->external_id);
    Cache::driver('chat')->tags([$workspaceTag, 'session-a'])->forever('session-a', ['conversations' => []]);

    app(DeleteUserAccountAction::class)->execute($owner);

    expect(FormSubmission::query()->whereKey($lead->id)->exists())->toBeFalse()
        ->and(DB::table('personal_access_tokens')->whereIn('tokenable_id', [$owner->id, $employee->id])->where('tokenable_type', $owner->getMorphClass())->exists())->toBeFalse()
        ->and(DB::table('sessions')->whereIn('user_id', [$owner->id, $employee->id])->exists())->toBeFalse()
        ->and(ModelSetting::query()->whereKey($settings)->exists())->toBeFalse()
        ->and(Cache::driver('chat')->tags([$workspaceTag, 'session-a'])->has('session-a'))->toBeFalse();
});

it('leaves other workspaces untouched while tearing one down', function (): void {
    $owner = User::factory()->create();
    $otherAgent = Agent::factory()->create();
    $otherOwner = $otherAgent->workspace->owner;
    $otherEmployee = User::factory()->employee($otherOwner)->create();
    $chatter = Chatter::factory()->ofWorkspace($owner)->create(['email' => 'both@example.test']);
    $otherChatter = Chatter::factory()->ofWorkspace($otherOwner)->create(['email' => 'both@example.test']);
    MessageLimit::factory()->forChatter($otherChatter)->create();
    $otherSetting = duaSetting($otherAgent)->id;
    $survivorTag = ChatCache::workspaceTag($otherAgent->workspace->external_id);
    Cache::driver('chat')->tags([$survivorTag, 'session-b'])->forever('session-b', ['conversations' => []]);

    app(DeleteUserAccountAction::class)->execute($owner);

    expect(Chatter::query()->whereKey($chatter->id)->exists())->toBeFalse()
        ->and(User::query()->whereKey($otherOwner->id)->exists())->toBeTrue()
        ->and(User::query()->whereKey($otherEmployee->id)->exists())->toBeTrue()
        ->and(Chatter::query()->whereKey($otherChatter->id)->exists())->toBeTrue()
        ->and(MessageLimit::query()->where('chatter_id', $otherChatter->id)->exists())->toBeTrue()
        ->and(ModelSetting::query()->find($otherSetting))->not->toBeNull()
        ->and(Cache::driver('chat')->tags([$survivorTag, 'session-b'])->has('session-b'))->toBeTrue();
});
