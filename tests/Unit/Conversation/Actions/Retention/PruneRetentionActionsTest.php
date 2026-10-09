<?php

use Carbon\CarbonInterface;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Retention\PruneAgentActivityAction;
use Domain\Conversation\Actions\Retention\PruneExpiredConversationsAction;
use Domain\Conversation\Actions\Retention\PruneToolApprovalCheckpointsAction;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Enums\ToolApprovalStatus;
use Domain\Conversation\Models\AgentActivity;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Models\ToolApprovalCheckpoint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config(['cache.stores.chat' => ['driver' => 'array']]);
});

function retentionConversation(array $attributes = []): Conversation
{
    $agent = Agent::factory()->create();

    return Conversation::factory()->withoutTitle()->create([
        'chat_session_id' => Session::factory()->create(['agent_id' => $agent->id])->id,
        'agent_id' => $agent->id,
        'source' => ConversationSource::Widget,
        ...$attributes,
    ]);
}

function retentionCheckpoint(ToolApprovalStatus $status, ?CarbonInterface $expiresAt, int $daysSinceUpdate = 0): ToolApprovalCheckpoint
{
    $checkpoint = ToolApprovalCheckpoint::create([
        'agent_id' => Agent::factory()->create()->id,
        'conversation_external_id' => (string) Str::uuid(),
        'assistant_message_id' => (string) Str::uuid(),
        'iteration' => 0,
        'status' => $status,
        'messages_snapshot' => [],
        'pending_calls' => [],
        'context' => [],
        'expires_at' => $expiresAt,
    ]);

    DB::table('tool_approval_checkpoints')->where('id', $checkpoint->id)->update(['updated_at' => now()->subDays($daysSinceUpdate)]);

    return $checkpoint;
}

it('soft-deletes conversations past their horizon and purges them after the grace window', function (): void {
    $expired = retentionConversation(['expires_at' => now()->subDay()]);
    $live = retentionConversation(['expires_at' => now()->addDay()]);
    $longGone = retentionConversation();
    $longGone->delete();
    DB::table('chat_conversations')->where('id', $longGone->id)->update(['deleted_at' => now()->subDays(40)]);

    $result = app(PruneExpiredConversationsAction::class)->execute(chunk: 50, graceDays: 30);

    expect($result->expired)->toBe(1)
        ->and($result->purged)->toBe(1)
        ->and(Conversation::withTrashed()->find($expired->id)->trashed())->toBeTrue()
        ->and($live->fresh()->trashed())->toBeFalse()
        ->and(Conversation::withTrashed()->find($longGone->id))->toBeNull();
});

it('removes anonymous sessions left empty for a long time', function (): void {
    $agent = Agent::factory()->create();
    $empty = Session::factory()->create(['agent_id' => $agent->id, 'user_id' => null]);
    DB::table('chat_sessions')->where('id', $empty->id)->update(['created_at' => now()->subDays(PruneExpiredConversationsAction::ORPHAN_SESSION_DAYS + 1)]);

    $result = app(PruneExpiredConversationsAction::class)->execute(chunk: 50, graceDays: 30);

    expect($result->orphanSessions)->toBe(1)
        ->and(Session::query()->find($empty->id))->toBeNull();
});

it('deletes expired checkpoints and resolved ones past the grace period', function (): void {
    $pending = retentionCheckpoint(ToolApprovalStatus::Pending, now()->addHour());
    $expired = retentionCheckpoint(ToolApprovalStatus::Pending, now()->subMinute());
    $recentlyResolved = retentionCheckpoint(ToolApprovalStatus::Resolved, now()->addHour(), daysSinceUpdate: 1);
    $oldResolved = retentionCheckpoint(ToolApprovalStatus::Resolved, now()->addHour(), daysSinceUpdate: 5);

    expect(app(PruneToolApprovalCheckpointsAction::class)->execute(graceDays: 3))->toBe(2)
        ->and(ToolApprovalCheckpoint::query()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$pending->id, $recentlyResolved->id])->sort()->values()->all());
});

it('deletes audit rows older than the retention window', function (): void {
    $agent = Agent::factory()->create();
    $row = fn (int $daysOld): AgentActivity => AgentActivity::create([
        'agent_id' => $agent->id,
        'agent_name' => $agent->name,
        'workspace_id' => $agent->user_id,
        'trace_id' => (string) Str::uuid(),
        'tool_call_id' => 'call_'.Str::random(6),
        'tool_name' => 'get_weather',
        'tool_kind' => 'function',
        'outcome' => 'auto',
        'gated' => false,
        'is_error' => false,
        'arguments_truncated' => false,
        'result_truncated' => false,
        'created_at' => now()->subDays($daysOld),
    ]);

    $old = $row(100);
    $recent = $row(10);

    expect(app(PruneAgentActivityAction::class)->execute(retentionDays: 90))->toBe(1)
        ->and(AgentActivity::query()->find($old->id))->toBeNull()
        ->and(AgentActivity::query()->find($recent->id))->not->toBeNull();
});
