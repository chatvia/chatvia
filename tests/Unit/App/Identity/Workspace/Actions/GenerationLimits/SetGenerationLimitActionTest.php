<?php

use App\Identity\Workspace\Actions\GenerationLimits\SetGenerationLimitAction;
use App\Identity\Workspace\Data\GenerationLimits\GenerationLimitData;
use App\Identity\Workspace\Models\GenerationLimit;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Carbon;

/**
 * Setting the generation limits creates or updates the workspace's one row
 * and keeps this month's counts; no cap of either kind removes the row. A
 * row left from an earlier month starts this month again at zero.
 */
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));
    $this->workspace = User::factory()->create()->workspace();
    $this->action = app(SetGenerationLimitAction::class);
});

it('starts a new or earlier-month row this month at zero, and keeps this month\'s count on a change', function (): void {
    $earlier = User::factory()->create()->workspace();
    $current = User::factory()->create()->workspace();
    GenerationLimit::factory()->forWorkspace($earlier)->lastMonth()->create(['generations_used' => 30]);
    GenerationLimit::factory()->forWorkspace($current)->create(['monthly_limit' => 40, 'generations_used' => 25]);

    $this->action->execute($this->workspace, new GenerationLimitData(monthlyLimit: 40));
    $this->action->execute($earlier, new GenerationLimitData(monthlyLimit: 60));
    $this->action->execute($current, new GenerationLimitData(monthlyLimit: 20));

    $created = GenerationLimit::query()->where('workspace_id', $this->workspace->id)->sole();
    $restarted = GenerationLimit::query()->where('workspace_id', $earlier->id)->sole();
    $changed = GenerationLimit::query()->where('workspace_id', $current->id)->sole();
    expect($created)->monthly_limit->toBe(40)->generations_used->toBe(0)
        ->and($created->period_starts_at->toDateTimeString())->toBe('2026-06-01 00:00:00')
        ->and($restarted->generations_used)->toBe(0)
        ->and($restarted->period_starts_at->toDateTimeString())->toBe('2026-06-01 00:00:00')
        ->and($changed)->monthly_limit->toBe(20)->generations_used->toBe(25)
        ->and($changed->reached())->toBeTrue();
});

it('sets a video cap on its own with pictures left free, and removes the row once neither kind has a cap', function (): void {
    GenerationLimit::factory()->forWorkspace($this->workspace)->create(['monthly_limit' => 40, 'generations_used' => 5, 'videos_used' => 2]);

    $this->action->execute($this->workspace, new GenerationLimitData(monthlyVideoLimit: 10));
    $limit = GenerationLimit::query()->where('workspace_id', $this->workspace->id)->sole();
    $this->action->execute($this->workspace, new GenerationLimitData);

    expect($limit->monthly_limit)->toBeNull()
        ->and($limit->monthly_video_limit)->toBe(10)
        ->and($limit->generations_used)->toBe(5)
        ->and($limit->videosThisMonth())->toBe(2)
        ->and($limit->reached())->toBeFalse()
        ->and(GenerationLimit::query()->where('workspace_id', $this->workspace->id)->exists())->toBeFalse();
});
