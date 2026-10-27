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

it('creates a limit that starts this month at zero', function (): void {
    $this->action->execute($this->workspace, new GenerationLimitData(monthlyLimit: 40));

    $limit = GenerationLimit::query()->where('workspace_id', $this->workspace->id)->sole();

    expect($limit->monthly_limit)->toBe(40)
        ->and($limit->generations_used)->toBe(0)
        ->and($limit->period_starts_at->toDateTimeString())->toBe('2026-06-01 00:00:00');
});

it('keeps what was made this month when the limit changes', function (): void {
    GenerationLimit::factory()->forWorkspace($this->workspace)->create(['monthly_limit' => 40, 'generations_used' => 25]);

    $this->action->execute($this->workspace, new GenerationLimitData(monthlyLimit: 20));

    $limit = GenerationLimit::query()->where('workspace_id', $this->workspace->id)->sole();

    expect($limit->monthly_limit)->toBe(20)
        ->and($limit->generations_used)->toBe(25)
        ->and($limit->reached())->toBeTrue();
});

it('starts a row of an earlier month again at zero', function (): void {
    GenerationLimit::factory()->forWorkspace($this->workspace)->lastMonth()->create(['generations_used' => 30]);

    $this->action->execute($this->workspace, new GenerationLimitData(monthlyLimit: 60));

    $limit = GenerationLimit::query()->where('workspace_id', $this->workspace->id)->sole();

    expect($limit->generations_used)->toBe(0)
        ->and($limit->period_starts_at->toDateTimeString())->toBe('2026-06-01 00:00:00');
});

it('removes the limits when neither kind has a cap', function (): void {
    GenerationLimit::factory()->forWorkspace($this->workspace)->create();

    $this->action->execute($this->workspace, new GenerationLimitData);

    expect(GenerationLimit::query()->where('workspace_id', $this->workspace->id)->exists())->toBeFalse();
});

it('sets a video cap on its own, with pictures and audio left free', function (): void {
    GenerationLimit::factory()->forWorkspace($this->workspace)->create(['monthly_limit' => 40, 'generations_used' => 5, 'videos_used' => 2]);

    $this->action->execute($this->workspace, new GenerationLimitData(monthlyVideoLimit: 10));

    $limit = GenerationLimit::query()->where('workspace_id', $this->workspace->id)->sole();

    expect($limit->monthly_limit)->toBeNull()
        ->and($limit->monthly_video_limit)->toBe(10)
        ->and($limit->generations_used)->toBe(5)
        ->and($limit->videosThisMonth())->toBe(2)
        ->and($limit->reached())->toBeFalse();
});
