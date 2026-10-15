<?php

/**
 * DeleteUserAccountAction: tearing down a workspace removes the workspace row,
 * its owner, teammates, chatters (by FK cascade), message limits, storage
 * trees (`workspaces/{ext}`, each member's `users/{ext}` and the chatters'
 * `chatters/{ext}` avatars), leads, tokens, sessions and chat memory, and
 * leaves every other workspace alone.
 */

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Actions\Account\DeleteUserAccountAction;
use App\Identity\Workspace\Actions\MessageLimits\SetMessageLimitAction;
use App\Identity\Workspace\Models\MessageLimit;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;
use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Agent\Models\ModelSetting;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session as ChatSession;
use Domain\Conversation\Services\Memory\ChatCache;
use Domain\Form\Models\FormSubmission;
use Domain\Membership\Team\Models\Team;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    // The teardown wipes workspaces/{external_id} and users/{external_id} on
    // the default and assets disks; fake both so the assertions stay hermetic
    // and never hit real S3 / CDN storage.
    Storage::fake();
    Storage::fake('assets');
});

it('hard-deletes the user row and its workspace', function (): void {
    $user = User::factory()->create();
    $workspaceId = $user->workspace()->id;

    app(DeleteUserAccountAction::class)->execute($user);

    expect(User::whereKey($user->id)->exists())->toBeFalse()
        ->and(Workspace::whereKey($workspaceId)->exists())->toBeFalse();
});

it('does not touch unrelated users', function (): void {
    $target = User::factory()->create();
    $bystander = User::factory()->create();

    app(DeleteUserAccountAction::class)->execute($target);

    expect(User::whereKey($bystander->id)->exists())->toBeTrue();
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

    $setLimit = app(SetMessageLimitAction::class);
    $setLimit->execute($workspace, 1000);
    $setLimit->execute($chatter, 50);

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

it('wipes the chatters avatar trees and keeps those of other workspaces', function (): void {
    $owner = User::factory()->create();
    $chatter = Chatter::factory()->ofWorkspace($owner)->create();
    $otherChatter = Chatter::factory()->create();

    $photo = 'chatters/'.$chatter->external_id.'/profile-photos/avatar.jpg';
    $otherPhoto = 'chatters/'.$otherChatter->external_id.'/profile-photos/avatar.jpg';
    Storage::disk('assets')->put($photo, 'img');
    Storage::disk('assets')->put($otherPhoto, 'img');

    app(DeleteUserAccountAction::class)->execute($owner);

    Storage::disk('assets')->assertMissing($photo);
    Storage::disk('assets')->assertExists($otherPhoto);
});

it('wipes each member own storage tree and keeps those of other workspaces', function (): void {
    $owner = User::factory()->create();
    $employee = User::factory()->employee($owner)->create();
    $bystander = User::factory()->create();

    $ownerPhoto = 'users/'.$owner->external_id.'/profile-photos/me.jpg';
    $employeeExport = 'users/'.$employee->external_id.'/data-exports/export.zip';
    $bystanderPhoto = 'users/'.$bystander->external_id.'/profile-photos/me.jpg';
    Storage::disk('assets')->put($ownerPhoto, 'img');
    Storage::put($employeeExport, 'zip');
    Storage::disk('assets')->put($bystanderPhoto, 'img');

    app(DeleteUserAccountAction::class)->execute($owner);

    Storage::disk('assets')->assertMissing($ownerPhoto);
    Storage::assertMissing($employeeExport);
    Storage::disk('assets')->assertExists($bystanderPhoto);
});

it('leaves other workspaces untouched while tearing one down', function (): void {
    $owner = User::factory()->create();
    $otherOwner = User::factory()->create();
    $otherEmployee = User::factory()->employee($otherOwner)->create();

    // The same email is a chatter in both workspaces: only this one goes.
    $chatter = Chatter::factory()->ofWorkspace($owner)->create(['email' => 'both@example.test']);
    $otherChatter = Chatter::factory()->ofWorkspace($otherOwner)->create(['email' => 'both@example.test']);
    app(SetMessageLimitAction::class)->execute($otherChatter, 20);

    app(DeleteUserAccountAction::class)->execute($owner);

    expect(User::whereKey($otherOwner->id)->exists())->toBeTrue()
        ->and(User::whereKey($otherEmployee->id)->exists())->toBeTrue()
        ->and(Chatter::whereKey($chatter->id)->exists())->toBeFalse()
        ->and(Chatter::whereKey($otherChatter->id)->exists())->toBeTrue()
        ->and(MessageLimit::query()->where('chatter_id', $otherChatter->id)->exists())->toBeTrue();
});

it('wipes conversation attachment files that the FK cascade would otherwise orphan on S3', function (): void {
    // chat_attachments rows go by FK cascade with their conversation, which
    // never fires the Attachment deleting-observer. The files live under the
    // workspace prefix, so the storage wipe removes them.
    $agent = Agent::factory()->create();
    $owner = $agent->workspace->owner;

    $conversation = Conversation::factory()->create(['agent_id' => $agent->id, 'source' => ConversationSource::HostedChat]);

    $filepath = "workspaces/{$agent->workspace->external_id}/agents/{$agent->external_id}/attachments/pending/doc.pdf";
    Storage::disk(Attachment::DISK)->put($filepath, 'data');
    $attachment = Attachment::create([
        'chat_conversation_id' => $conversation->id,
        'filepath' => $filepath,
        'filename' => 'doc.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 4,
    ]);

    Storage::disk(Attachment::DISK)->assertExists($filepath);

    app(DeleteUserAccountAction::class)->execute($owner);

    Storage::disk(Attachment::DISK)->assertMissing($filepath);
    expect(Attachment::whereKey($attachment->id)->exists())->toBeFalse();
});

it('wipes the workspace knowledge-file storage tree the FK cascade would orphan', function (): void {
    // Sources are removed by FK cascade, which never fires their observers — so
    // the knowledge files under workspaces/{external_id}/sources/… would leak.
    // The teardown wipes the whole workspaces/{external_id} tree to catch them.
    $agent = Agent::factory()->create();
    $owner = $agent->workspace->owner;

    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
    $knowledgePath = $file->filepath;
    expect($knowledgePath)->toStartWith('workspaces/'.$agent->workspace->external_id.'/sources/');
    Storage::put($knowledgePath, 'pdf-bytes');
    Storage::assertExists($knowledgePath);

    app(DeleteUserAccountAction::class)->execute($owner);

    Storage::assertMissing($knowledgePath);
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

it('deletes conversations and sessions in small id-ordered batches by workspace', function (): void {
    $agent = Agent::factory()->create();
    $owner = $agent->workspace->owner;
    $otherAgent = Agent::factory()->create();

    // Five conversations, each with its own session and two messages.
    $conversations = Conversation::factory()->count(5)->create(['agent_id' => $agent->id]);
    $conversations->each(fn (Conversation $conversation) => Message::factory()->count(2)->create(['chat_conversation_id' => $conversation->id]));
    $kept = Conversation::factory()->create(['agent_id' => $otherAgent->id]);

    $deletes = ['chat_conversations' => 0, 'chat_sessions' => 0];
    DB::listen(function (QueryExecuted $query) use (&$deletes): void {
        foreach (array_keys($deletes) as $table) {
            if (str_starts_with($query->sql, "DELETE FROM {$table} WHERE id IN (SELECT id FROM {$table} WHERE workspace_id = ? ORDER BY id LIMIT 2)")) {
                $deletes[$table]++;
            }
        }
    });

    new DeleteUserAccountAction(conversationBatch: 2, rowBatch: 2)->execute($owner);

    // 5 rows in batches of 2 → 3 batches, then 1 that finds nothing.
    expect($deletes)->toBe(['chat_conversations' => 4, 'chat_sessions' => 4])
        ->and(Conversation::withTrashed()->whereKey($conversations->modelKeys())->exists())->toBeFalse()
        ->and(Message::query()->whereIn('chat_conversation_id', $conversations->modelKeys())->exists())->toBeFalse()
        ->and(Conversation::find($kept->id))->not->toBeNull();
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
