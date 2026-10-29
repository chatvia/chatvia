<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Actions\Account\DeleteEmployeeAction;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Agent\Prompt\Models\AgentPrompt;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session as ChatSession;
use Domain\Membership\Team\Actions\Team\CreateDefaultTeamAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * DeleteEmployeeAction: removes one teammate and what the FK graph cannot
 * reach (tokens, HTTP sessions, team membership, their own files), keeps the workspace
 * resources the teammate created (they are keyed to the workspace), and
 * leaves the owner, other teammates and the workspace's chatters (a separate
 * table) alone.
 */
it('deletes the teammate with their tokens, sessions and team membership, and leaves the owner, siblings and a same-id chatter alone', function (): void {
    $owner = User::factory()->create();
    $team = app(CreateDefaultTeamAction::class)->execute($owner);
    $employee = User::factory()->employee($owner)->create(['id' => 900001]);
    $sibling = User::factory()->employee($owner)->create();
    $team->members()->attach($employee->id, ['role_id' => $team->roles()->firstOrFail()->id]);
    $employee->createToken('cli');
    DB::table('sessions')->insert([
        'id' => 'sess-emp-1',
        'user_id' => $employee->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'payload' => 'x',
        'last_activity' => now()->timestamp,
    ]);
    $twin = Chatter::factory()->ofWorkspace($owner)->create(['id' => $employee->id]);
    $agent = Agent::factory()->create(['workspace_id' => $owner->workspace()->id]);
    $chatterSession = ChatSession::factory()->forChatter($twin)->create(['agent_id' => $agent->id]);
    $staffSession = ChatSession::factory()->create(['agent_id' => $agent->id, 'user_id' => $employee->id]);

    app(DeleteEmployeeAction::class)->execute($employee);

    expect(User::query()->whereKey($employee->id)->exists())->toBeFalse()
        ->and(DB::table('personal_access_tokens')->where('tokenable_id', $employee->id)->where('tokenable_type', $employee->getMorphClass())->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $employee->id)->exists())->toBeFalse()
        ->and(DB::table('team_user')->where('user_id', $employee->id)->exists())->toBeFalse()
        ->and(ChatSession::query()->whereKey($staffSession->id)->exists())->toBeFalse()
        ->and(User::query()->whereKey($owner->id)->exists())->toBeTrue()
        ->and(User::query()->whereKey($sibling->id)->exists())->toBeTrue()
        ->and(Chatter::query()->whereKey($twin->id)->exists())->toBeTrue()
        ->and(ChatSession::query()->whereKey($chatterSession->id)->exists())->toBeTrue();
});

it('keeps the workspace resources and handled conversations of the teammate, only detaching them', function (): void {
    $owner = User::factory()->create();
    $employee = User::factory()->employee($owner)->create();
    $prompt = $employee->workspace()->prompts()->create(['name' => 'Shared prompt', 'prompt' => 'Hello']);
    $chatter = Chatter::factory()->ofWorkspace($owner)->create();
    $agent = Agent::factory()->create(['workspace_id' => $owner->workspace()->id]);
    $session = ChatSession::factory()->forChatter($chatter)->create(['agent_id' => $agent->id]);
    $conversation = Conversation::factory()->create(['chat_session_id' => $session->id, 'source' => ConversationSource::HostedChat]);
    DB::table('chat_conversations')->where('id', $conversation->id)->update(['handoff_user_id' => $employee->id]);

    app(DeleteEmployeeAction::class)->execute($employee);

    expect(AgentPrompt::query()->whereKey($prompt->id)->value('workspace_id'))->toBe($owner->workspace()->id)
        ->and(DB::table('chat_conversations')->where('id', $conversation->id)->exists())->toBeTrue()
        ->and(DB::table('chat_conversations')->where('id', $conversation->id)->value('handoff_user_id'))->toBeNull()
        ->and(ChatSession::query()->whereKey($session->id)->exists())->toBeTrue();
});

it('wipes the employee\'s own files on every disk and keeps everyone else\'s', function (): void {
    Storage::fake('local');
    Storage::fake('assets');
    Storage::fake('private');
    $owner = User::factory()->create();
    $employee = User::factory()->employee($owner)->create();
    $sibling = User::factory()->employee($owner)->create();
    $export = $employee->storageRoot().'/data-exports/export.zip';
    $photo = $employee->storageRoot().'/profile-photos/me.jpg';
    $private = $employee->storageRoot().'/notes/file.txt';
    $siblingPhoto = $sibling->storageRoot().'/profile-photos/me.jpg';
    $workspaceFile = 'workspaces/'.$owner->workspace()->external_id.'/sources/files/handbook.pdf';
    Storage::disk('local')->put($export, 'zip');
    Storage::disk('assets')->put($photo, 'img');
    Storage::disk('private')->put($private, 'txt');
    Storage::disk('assets')->put($siblingPhoto, 'img');
    Storage::disk('private')->put($workspaceFile, 'pdf');

    app(DeleteEmployeeAction::class)->execute($employee);

    Storage::disk('local')->assertMissing($export);
    Storage::disk('assets')->assertMissing($photo);
    Storage::disk('private')->assertMissing($private);
    Storage::disk('assets')->assertExists($siblingPhoto);
    Storage::disk('private')->assertExists($workspaceFile);
});
