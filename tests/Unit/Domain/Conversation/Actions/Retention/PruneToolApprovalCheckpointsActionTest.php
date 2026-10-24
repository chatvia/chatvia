<?php

use Carbon\CarbonInterface;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Retention\PruneToolApprovalCheckpointsAction;
use Domain\Conversation\Enums\ToolApprovalStatus;
use Domain\Conversation\Models\ToolApprovalCheckpoint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The checkpoint sweep: a paused turn's checkpoint goes once it expired
 * unanswered, or a grace period after it was resolved, in batches. A pending
 * unexpired checkpoint and a freshly resolved one stay.
 */
function ptacCheckpoint(ToolApprovalStatus $status, ?CarbonInterface $expiresAt, int $daysSinceUpdate = 0): ToolApprovalCheckpoint
{
    $agent = Agent::factory()->create();

    $checkpoint = ToolApprovalCheckpoint::create([
        'workspace_id' => $agent->workspace_id,
        'agent_id' => $agent->id,
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

it('deletes expired checkpoints and resolved ones past the grace period', function (): void {
    $pending = ptacCheckpoint(ToolApprovalStatus::Pending, now()->addHour());
    ptacCheckpoint(ToolApprovalStatus::Pending, now()->subMinute());
    $recentlyResolved = ptacCheckpoint(ToolApprovalStatus::Resolved, now()->addHour(), daysSinceUpdate: 1);
    ptacCheckpoint(ToolApprovalStatus::Resolved, now()->addHour(), daysSinceUpdate: 5);

    expect(app(PruneToolApprovalCheckpointsAction::class)->execute(graceDays: 3))->toBe(2)
        ->and(ToolApprovalCheckpoint::query()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$pending->id, $recentlyResolved->id])->sort()->values()->all());
});

it('deletes checkpoints in batches', function (): void {
    $stale = collect(range(1, 3))->map(fn (): ToolApprovalCheckpoint => ptacCheckpoint(ToolApprovalStatus::Pending, now()->subMinute()));

    expect(app(PruneToolApprovalCheckpointsAction::class)->execute(graceDays: 3, batchSize: 1))->toBe(3)
        ->and(ToolApprovalCheckpoint::query()->whereKey($stale->pluck('id'))->exists())->toBeFalse();
});
