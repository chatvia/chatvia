<?php

use App\Identity\Workspace\Models\GenerationLimit;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\GenerationAllowance;
use Domain\Conversation\Enums\GeneratedMediaKind;
use Illuminate\Support\Carbon;

/**
 * The monthly generation caps: each file takes a slot while one is left, a
 * new month starts the count again, a failed file gives its slot back (in
 * its own month only), and a workspace without a cap always may. Videos
 * count against a cap of their own.
 */
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));
    $this->workspace = User::factory()->create()->workspace();
    $this->allowance = app(GenerationAllowance::class);
});

function gaUsed(int $workspaceId): int
{
    return (int) GenerationLimit::query()->where('workspace_id', $workspaceId)->value('generations_used');
}

it('always allows a workspace without a cap', function (): void {
    expect($this->allowance->reserve($this->workspace->id, GeneratedMediaKind::Image))->toBeTrue()
        ->and(GenerationLimit::query()->count())->toBe(0);
});

it('takes a slot for each file until the cap is reached', function (): void {
    GenerationLimit::factory()->forWorkspace($this->workspace)->create(['monthly_limit' => 2]);

    expect($this->allowance->reserve($this->workspace->id, GeneratedMediaKind::Image))->toBeTrue()
        ->and($this->allowance->reserve($this->workspace->id, GeneratedMediaKind::Image))->toBeTrue()
        ->and($this->allowance->reserve($this->workspace->id, GeneratedMediaKind::Image))->toBeFalse()
        ->and(gaUsed($this->workspace->id))->toBe(2);
});

it('starts the count again in a new month', function (): void {
    GenerationLimit::factory()->forWorkspace($this->workspace)->lastMonth()->reached()->create(['monthly_limit' => 3]);

    expect($this->allowance->reserve($this->workspace->id, GeneratedMediaKind::Image))->toBeTrue();

    $limit = GenerationLimit::query()->where('workspace_id', $this->workspace->id)->sole();

    expect($limit->generations_used)->toBe(1)
        ->and($limit->period_starts_at->toDateTimeString())->toBe('2026-06-01 00:00:00');
});

it('never passes the cap, however the slots are asked for', function (): void {
    GenerationLimit::factory()->forWorkspace($this->workspace)->create(['monthly_limit' => 5, 'generations_used' => 3]);

    $granted = collect(range(1, 6))->filter(fn (): bool => app(GenerationAllowance::class)->reserve($this->workspace->id, GeneratedMediaKind::Image))->count();

    expect($granted)->toBe(2)
        ->and(gaUsed($this->workspace->id))->toBe(5);
});

it('gives back the slot of a failed file', function (): void {
    GenerationLimit::factory()->forWorkspace($this->workspace)->create(['monthly_limit' => 2, 'generations_used' => 2]);

    $this->allowance->release($this->workspace->id, GeneratedMediaKind::Image, now()->subMinute());

    expect(gaUsed($this->workspace->id))->toBe(1)
        ->and($this->allowance->reserve($this->workspace->id, GeneratedMediaKind::Image))->toBeTrue();
});

it('does not give back a slot of an earlier month, nor go below zero', function (): void {
    GenerationLimit::factory()->forWorkspace($this->workspace)->create(['generations_used' => 1]);

    $this->allowance->release($this->workspace->id, GeneratedMediaKind::Image, Carbon::parse('2026-05-31 23:59:00'));
    expect(gaUsed($this->workspace->id))->toBe(1);

    $this->allowance->release($this->workspace->id, GeneratedMediaKind::Image, now());
    $this->allowance->release($this->workspace->id, GeneratedMediaKind::Image, now());
    expect(gaUsed($this->workspace->id))->toBe(0);
});

function gaVideos(int $workspaceId): int
{
    return (int) GenerationLimit::query()->where('workspace_id', $workspaceId)->value('videos_used');
}

it('counts videos against their own cap, apart from pictures and audio', function (): void {
    GenerationLimit::factory()->forWorkspace($this->workspace)->create(['monthly_limit' => 1, 'monthly_video_limit' => 1]);

    expect($this->allowance->reserve($this->workspace->id, GeneratedMediaKind::Video))->toBeTrue()
        ->and($this->allowance->reserve($this->workspace->id, GeneratedMediaKind::Video))->toBeFalse()
        ->and($this->allowance->reserve($this->workspace->id, GeneratedMediaKind::Audio))->toBeTrue()
        ->and(gaVideos($this->workspace->id))->toBe(1)
        ->and(gaUsed($this->workspace->id))->toBe(1);
});

it('leaves a kind without a cap free, and still counts it', function (): void {
    GenerationLimit::factory()->forWorkspace($this->workspace)->create(['monthly_limit' => null, 'monthly_video_limit' => 1]);

    expect($this->allowance->reserve($this->workspace->id, GeneratedMediaKind::Image))->toBeTrue()
        ->and($this->allowance->reserve($this->workspace->id, GeneratedMediaKind::Image))->toBeTrue()
        ->and(gaUsed($this->workspace->id))->toBe(2);
});

it('starts both counts again when a new month begins with a video', function (): void {
    GenerationLimit::factory()->forWorkspace($this->workspace)->lastMonth()->create(['generations_used' => 7, 'videos_used' => 3, 'monthly_video_limit' => 3]);

    expect($this->allowance->reserve($this->workspace->id, GeneratedMediaKind::Video))->toBeTrue()
        ->and(gaVideos($this->workspace->id))->toBe(1)
        ->and(gaUsed($this->workspace->id))->toBe(0);
});

it('gives back the slot of a failed video', function (): void {
    GenerationLimit::factory()->forWorkspace($this->workspace)->create(['generations_used' => 2, 'videos_used' => 2, 'monthly_video_limit' => 2]);

    $this->allowance->release($this->workspace->id, GeneratedMediaKind::Video, now());

    expect(gaVideos($this->workspace->id))->toBe(1)
        ->and(gaUsed($this->workspace->id))->toBe(2);
});
