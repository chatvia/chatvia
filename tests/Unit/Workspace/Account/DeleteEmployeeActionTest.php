<?php

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

it('reassigns workspace resources the employee authored to the owner instead of cascading them away', function (): void {
    $owner = User::factory()->create();
    $employee = User::factory()->employee($owner)->create();

    // A prompt created the old (mis-keyed) way: owned by the employee.
    $prompt = $employee->prompts()->create(['name' => 'Shared prompt', 'prompt' => 'Hello']);

    app(DeleteEmployeeAction::class)->execute($employee);

    expect(AgentPrompt::whereKey($prompt->id)->exists())->toBeTrue()
        ->and(AgentPrompt::whereKey($prompt->id)->value('user_id'))->toBe($owner->id);
});

it('detaches the employee from conversations they handled without deleting the conversation', function (): void {
    $owner = User::factory()->create();
    $employee = User::factory()->employee($owner)->create();
    $chatter = User::factory()->chatter($owner)->create();
    $agent = Agent::factory()->create(['user_id' => $owner->id]);

    $session = ChatSession::create(['agent_id' => $agent->id, 'user_id' => $chatter->id]);
    $conversation = Conversation::create([
        'chat_session_id' => $session->id,
        'agent_id' => $agent->id,
        'source' => ConversationSource::HostedChat,
        'title' => 'Handled by employee',
    ]);
    // Set the handoff actor directly so the test does not depend on the
    // conversation model's handoff mass-assignment rules.
    DB::table('chat_conversations')->where('id', $conversation->id)->update(['handoff_user_id' => $employee->id]);

    app(DeleteEmployeeAction::class)->execute($employee);

    expect(DB::table('chat_conversations')->where('id', $conversation->id)->exists())->toBeTrue()
        ->and(DB::table('chat_conversations')->where('id', $conversation->id)->value('handoff_user_id'))->toBeNull();
});
