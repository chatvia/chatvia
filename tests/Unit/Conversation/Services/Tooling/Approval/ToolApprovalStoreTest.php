<?php

use Domain\Conversation\Data\Tooling\PendingToolCallData;
use Domain\Conversation\Enums\ToolApprovalStatus;
use Domain\Conversation\Models\ToolApprovalCheckpoint;
use Domain\Conversation\Services\Tooling\Approval\ToolApprovalStore;
use Illuminate\Support\Str;
use Tests\Support\Conversation\TestChatContext;

/**
 * A turn paused for approval is saved as a checkpoint, and a checkpoint is
 * claimed exactly once while it is pending and unexpired, so a double submit
 * can never run the tools twice.
 */
function tasCheckpoint(): ToolApprovalCheckpoint
{
    $pending = PendingToolCallData::fromArray([
        'tool_call_id' => 'call_1',
        'name' => 'close_ticket',
        'arguments' => '{"id":12}',
        'requires_approval' => true,
        'kind' => 'function',
        'label' => 'Close ticket',
    ]);

    return app(ToolApprovalStore::class)->persist(TestChatContext::make(), [['role' => 'user', 'content' => 'Close it']], [$pending], iteration: 0);
}

it('saves a paused turn as a pending checkpoint that expires after the configured time', function (): void {
    $this->freezeSecond();
    config(['chatvia.agent.tools.approval_ttl_minutes' => 30]);

    $checkpoint = tasCheckpoint()->fresh();

    expect($checkpoint->status)->toBe(ToolApprovalStatus::Pending)
        ->and($checkpoint->expires_at->equalTo(now()->addMinutes(30)))->toBeTrue()
        ->and($checkpoint->pendingToolCalls()[0]->name)->toBe('close_ticket')
        ->and($checkpoint->messages_snapshot)->toBe([['role' => 'user', 'content' => 'Close it']]);
});

it('hands a pending checkpoint out exactly once', function (): void {
    $checkpoint = tasCheckpoint();
    $store = app(ToolApprovalStore::class);

    $claimed = $store->claim($checkpoint->external_id);

    expect($claimed?->id)->toBe($checkpoint->id)
        ->and($checkpoint->fresh()->status)->toBe(ToolApprovalStatus::Resolved)
        ->and($store->claim($checkpoint->external_id))->toBeNull();
});

it('does not hand out an expired checkpoint', function (): void {
    $checkpoint = tasCheckpoint();
    $checkpoint->forceFill(['expires_at' => now()->subMinute()])->save();

    expect(app(ToolApprovalStore::class)->claim($checkpoint->external_id))->toBeNull()
        ->and($checkpoint->fresh()->status)->toBe(ToolApprovalStatus::Pending);
});

it('hands out nothing for an unknown checkpoint', function (): void {
    expect(app(ToolApprovalStore::class)->claim((string) Str::uuid()))->toBeNull();
});
