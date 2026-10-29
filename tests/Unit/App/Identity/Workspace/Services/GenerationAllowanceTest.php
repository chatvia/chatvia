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

function gaVideos(int $workspaceId): int
{
    return (int) GenerationLimit::query()->where('workspace_id', $workspaceId)->value('videos_used');
}

it('takes a slot per file up to the cap and never past it, and lets a workspace or kind without a cap through', function (): void {
    $uncapped = User::factory()->create()->workspace();
    $racing = User::factory()->create()->workspace();
    $videoOnly = User::factory()->create()->workspace();
    GenerationLimit::factory()->forWorkspace($this->workspace)->create(['monthly_limit' => 2]);
    GenerationLimit::factory()->forWorkspace($racing)->create(['monthly_limit' => 5, 'generations_used' => 3]);
    GenerationLimit::factory()->forWorkspace($videoOnly)->create(['monthly_limit' => null, 'monthly_video_limit' => 1]);

    $reserved = collect(range(1, 3))->map(fn (): bool => $this->allowance->reserve($this->workspace->id, GeneratedMediaKind::Image))->all();
    $granted = collect(range(1, 6))->filter(fn (): bool => app(GenerationAllowance::class)->reserve($racing->id, GeneratedMediaKind::Image))->count();
    $uncappedAllowed = $this->allowance->reserve($uncapped->id, GeneratedMediaKind::Image);
    $freeKind = [$this->allowance->reserve($videoOnly->id, GeneratedMediaKind::Image), $this->allowance->reserve($videoOnly->id, GeneratedMediaKind::Image)];

    expect($reserved)->toBe([true, true, false])
        ->and(gaUsed($this->workspace->id))->toBe(2)
        ->and($granted)->toBe(2)
        ->and(gaUsed($racing->id))->toBe(5)
        ->and($uncappedAllowed)->toBeTrue()
        ->and(GenerationLimit::query()->where('workspace_id', $uncapped->id)->exists())->toBeFalse()
        ->and($freeKind)->toBe([true, true])
        ->and(gaUsed($videoOnly->id))->toBe(2);
});

it('starts the counts again in a new month and gives back a failed file\'s slot of this month only, never below zero', function (): void {
    $newMonth = User::factory()->create()->workspace();
    $newMonthVideo = User::factory()->create()->workspace();
    $released = User::factory()->create()->workspace();
    GenerationLimit::factory()->forWorkspace($newMonth)->lastMonth()->reached()->create(['monthly_limit' => 3]);
    GenerationLimit::factory()->forWorkspace($newMonthVideo)->lastMonth()->create(['generations_used' => 7, 'videos_used' => 3, 'monthly_video_limit' => 3]);
    GenerationLimit::factory()->forWorkspace($this->workspace)->create(['monthly_limit' => 2, 'generations_used' => 2]);
    GenerationLimit::factory()->forWorkspace($released)->create(['generations_used' => 1]);

    $newMonthAllowed = $this->allowance->reserve($newMonth->id, GeneratedMediaKind::Image);
    $newMonthVideoAllowed = $this->allowance->reserve($newMonthVideo->id, GeneratedMediaKind::Video);
    $this->allowance->release($this->workspace->id, GeneratedMediaKind::Image, now()->subMinute());
    $roomAgain = $this->allowance->reserve($this->workspace->id, GeneratedMediaKind::Image);
    $this->allowance->release($released->id, GeneratedMediaKind::Image, Carbon::parse('2026-05-31 23:59:00'));
    $afterEarlierMonth = gaUsed($released->id);
    $this->allowance->release($released->id, GeneratedMediaKind::Image, now());
    $this->allowance->release($released->id, GeneratedMediaKind::Image, now());

    $limit = GenerationLimit::query()->where('workspace_id', $newMonth->id)->sole();
    expect($newMonthAllowed)->toBeTrue()
        ->and($limit->generations_used)->toBe(1)
        ->and($limit->period_starts_at->toDateTimeString())->toBe('2026-06-01 00:00:00')
        ->and($newMonthVideoAllowed)->toBeTrue()
        ->and(gaVideos($newMonthVideo->id))->toBe(1)
        ->and(gaUsed($newMonthVideo->id))->toBe(0)
        ->and($roomAgain)->toBeTrue()
        ->and($afterEarlierMonth)->toBe(1)
        ->and(gaUsed($released->id))->toBe(0);
});

it('counts videos against their own cap, apart from pictures and audio, and gives a failed video\'s slot back', function (): void {
    $released = User::factory()->create()->workspace();
    GenerationLimit::factory()->forWorkspace($this->workspace)->create(['monthly_limit' => 1, 'monthly_video_limit' => 1]);
    GenerationLimit::factory()->forWorkspace($released)->create(['generations_used' => 2, 'videos_used' => 2, 'monthly_video_limit' => 2]);

    $reserved = [
        $this->allowance->reserve($this->workspace->id, GeneratedMediaKind::Video),
        $this->allowance->reserve($this->workspace->id, GeneratedMediaKind::Video),
        $this->allowance->reserve($this->workspace->id, GeneratedMediaKind::Audio),
    ];
    $this->allowance->release($released->id, GeneratedMediaKind::Video, now());

    expect($reserved)->toBe([true, false, true])
        ->and(gaVideos($this->workspace->id))->toBe(1)
        ->and(gaUsed($this->workspace->id))->toBe(1)
        ->and(gaVideos($released->id))->toBe(1)
        ->and(gaUsed($released->id))->toBe(2);
});
