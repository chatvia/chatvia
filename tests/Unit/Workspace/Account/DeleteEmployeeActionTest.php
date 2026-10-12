<?php

/**
 * DeleteEmployeeAction: removes one teammate and what the FK graph cannot
 * reach (tokens, HTTP sessions, team membership), keeps the workspace
 * resources the teammate created (they are keyed to the workspace), and
 * leaves the owner, other teammates and the workspace's chatters (a separate
 * table) alone.
 */

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Actions\Account\DeleteEmployeeAction;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Agent\Prompt\Models\AgentPrompt;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session as ChatSession;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\DB;

it('hard-deletes the employee while leaving the owner and sibling teammates intact', function (): void {
    $owner = User::factory()->create();
    $employee = User::factory()->employee($owner)->create();
    $sibling = User::factory()->employee($owner)->create();

    app(DeleteEmployeeAction::class)->execute($employee);

    expect(User::whereKey($employee->id)->exists())->toBeFalse()
        ->and(User::whereKey($owner->id)->exists())->toBeTrue()
        ->and(User::whereKey($sibling->id)->exists())->toBeTrue();
});

it('deletes the Sanctum tokens, HTTP sessions and team membership the FK graph cannot reach', function (): void {
    $owner = User::factory()->create();
    $team = app(TeamService::class)->createDefaultTeam($owner);
    $role = $team->roles()->firstOrFail();

    $employee = User::factory()->employee($owner)->create();
    $team->members()->attach($employee->id, ['role_id' => $role->id]);

    $employee->createToken('cli');
    DB::table('sessions')->insert([
        'id' => 'sess-emp-1',
        'user_id' => $employee->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'payload' => 'x',
        'last_activity' => now()->timestamp,
    ]);

    app(DeleteEmployeeAction::class)->execute($employee);

    expect(DB::table('personal_access_tokens')->where('tokenable_id', $employee->id)->where('tokenable_type', $employee->getMorphClass())->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $employee->id)->exists())->toBeFalse()
        ->and(DB::table('team_user')->where('user_id', $employee->id)->exists())->toBeFalse();
});

it('keeps workspace resources the employee created instead of cascading them away', function (): void {
    $owner = User::factory()->create();
    $employee = User::factory()->employee($owner)->create();

    // Created by the employee, but keyed to the shared workspace.
    $prompt = $employee->workspace()->prompts()->create(['name' => 'Shared prompt', 'prompt' => 'Hello']);

    app(DeleteEmployeeAction::class)->execute($employee);

    expect(AgentPrompt::whereKey($prompt->id)->exists())->toBeTrue()
        ->and(AgentPrompt::whereKey($prompt->id)->value('workspace_id'))->toBe($owner->workspace()->id);
});

it('detaches the employee from conversations they handled without deleting the conversation', function (): void {
    $owner = User::factory()->create();
    $employee = User::factory()->employee($owner)->create();
    $chatter = Chatter::factory()->ofWorkspace($owner)->create();
    $agent = Agent::factory()->create(['workspace_id' => $owner->workspace()->id]);

    $session = ChatSession::create(['workspace_id' => $agent->workspace_id, 'agent_id' => $agent->id, 'chatter_id' => $chatter->id]);
    $conversation = Conversation::create([
        'chat_session_id' => $session->id,
        'workspace_id' => $agent->workspace_id,
        'agent_id' => $agent->id,
        'source' => ConversationSource::HostedChat,
        'title' => 'Handled by employee',
    ]);
    // Set the handoff actor directly so the test does not depend on the
    // conversation model's handoff mass-assignment rules.
    DB::table('chat_conversations')->where('id', $conversation->id)->update(['handoff_user_id' => $employee->id]);

    app(DeleteEmployeeAction::class)->execute($employee);

    expect(DB::table('chat_conversations')->where('id', $conversation->id)->exists())->toBeTrue()
        ->and(DB::table('chat_conversations')->where('id', $conversation->id)->value('handoff_user_id'))->toBeNull()
        ->and(ChatSession::whereKey($session->id)->exists())->toBeTrue();
});

it('leaves the workspace chatters and their sessions alone, even one whose id equals the employee id', function (): void {
    $owner = User::factory()->create();
    $employee = User::factory()->employee($owner)->create();
    // Chatter and staff ids come from different tables and can be equal.
    $twin = Chatter::factory()->ofWorkspace($owner)->create(['id' => $employee->id]);
    $agent = Agent::factory()->create(['workspace_id' => $owner->workspace()->id]);

    $chatterSession = ChatSession::create(['workspace_id' => $agent->workspace_id, 'agent_id' => $agent->id, 'chatter_id' => $twin->id]);
    $staffSession = ChatSession::create(['workspace_id' => $agent->workspace_id, 'agent_id' => $agent->id, 'user_id' => $employee->id]);

    app(DeleteEmployeeAction::class)->execute($employee);

    expect(User::whereKey($employee->id)->exists())->toBeFalse()
        ->and(Chatter::whereKey($twin->id)->exists())->toBeTrue()
        ->and(ChatSession::whereKey($chatterSession->id)->exists())->toBeTrue()
        ->and(ChatSession::whereKey($staffSession->id)->exists())->toBeFalse();
});
