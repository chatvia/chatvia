<?php

use App\Admin\Issue\Models\Issue;
use App\Admin\Issue\Models\IssueOccurrence;

/**
 * The nightly prune of the Issues page (chatvia:prune:issues): occurrences
 * older than the retention go, and so do issues not seen since.
 */
it('deletes old occurrences and the issues not seen since', function (): void {
    $old = Issue::factory()->seenDaysAgo(40)->create();
    IssueOccurrence::factory()->create(['issue_id' => $old->id, 'occurred_at' => now()->subDays(40)]);
    $recent = Issue::factory()->create();
    $stale = IssueOccurrence::factory()->create(['issue_id' => $recent->id, 'occurred_at' => now()->subDays(40)]);
    $fresh = IssueOccurrence::factory()->create(['issue_id' => $recent->id]);

    $this->artisan('chatvia:prune:issues', ['--days' => 30])->assertSuccessful();

    expect(Issue::query()->pluck('id')->all())->toBe([$recent->id])
        ->and(IssueOccurrence::query()->pluck('id')->all())->toBe([$fresh->id])
        ->and(IssueOccurrence::query()->whereKey($stale->id)->exists())->toBeFalse();
});
