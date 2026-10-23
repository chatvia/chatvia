<?php

use Domain\Agent\Models\Agent;
use Domain\Security\Actions\UnblockVisitorAction;
use Domain\Security\Data\SecurityActorData;
use Domain\Security\Models\BlockedVisitor;
use Domain\Security\Models\SecurityAuditLog;

/**
 * Unblocking removes the block and writes that to the security audit log.
 */
it('removes the block and logs it', function (): void {
    $agent = Agent::factory()->create();
    $block = BlockedVisitor::factory()->create(['workspace_id' => $agent->workspace_id]);

    app(UnblockVisitorAction::class)->execute($block, SecurityActorData::forUser($agent->workspace->owner));

    expect(BlockedVisitor::query()->whereKey($block->id)->exists())->toBeFalse()
        ->and(SecurityAuditLog::query()
            ->where('workspace_id', $agent->workspace_id)
            ->where('action', 'visitor.unblocked')
            ->where('target_id', $block->external_id)
            ->exists())->toBeTrue();
});
