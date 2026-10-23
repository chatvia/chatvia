<?php

use App\Identity\Workspace\Models\Workspace;
use Domain\Security\Actions\PruneExpiredVisitorBlocksAction;
use Domain\Security\Models\BlockedVisitor;

/**
 * Blocks that ended more than the kept days ago are deleted; blocks still in
 * force, blocks without an end and blocks that just ended stay.
 */
it('deletes only blocks that ended long ago', function (): void {
    $workspace = Workspace::factory()->create();
    $old = BlockedVisitor::factory()->create(['workspace_id' => $workspace->id, 'expires_at' => now()->subDays(31)]);
    $recent = BlockedVisitor::factory()->create(['workspace_id' => $workspace->id, 'expires_at' => now()->subDays(2)]);
    $active = BlockedVisitor::factory()->create(['workspace_id' => $workspace->id]);
    $forever = BlockedVisitor::factory()->create(['workspace_id' => $workspace->id, 'expires_at' => null]);

    expect(app(PruneExpiredVisitorBlocksAction::class)->execute(30))->toBe(1)
        ->and(BlockedVisitor::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(BlockedVisitor::query()->whereKey([$recent->id, $active->id, $forever->id])->count())->toBe(3);
});
