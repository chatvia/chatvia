<?php

use App\Identity\Workspace\Models\GenerationLimit;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\GenerationAllowance;
use Illuminate\Support\Carbon;

/**
 * The monthly generation cap: each file takes a slot while one is left, a
 * new month starts the count again, a failed file gives its slot back (in
 * its own month only), and a workspace without a cap always may.
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
    expect($this->allowance->reserve($this->workspace->id))->toBeTrue()
        ->and(GenerationLimit::query()->count())->toBe(0);
});

it('takes a slot for each file until the cap is reached', function (): void {
    GenerationLimit::factory()->forWorkspace($this->workspace)->create(['monthly_limit' => 2]);

    expect($this->allowance->reserve($this->workspace->id))->toBeTrue()
        ->and($this->allowance->reserve($this->workspace->id))->toBeTrue()
        ->and($this->allowance->reserve($this->workspace->id))->toBeFalse()
        ->and(gaUsed($this->workspace->id))->toBe(2);
});

it('starts the count again in a new month', function (): void {
    GenerationLimit::factory()->forWorkspace($this->workspace)->lastMonth()->reached()->create(['monthly_limit' => 3]);

    expect($this->allowance->reserve($this->workspace->id))->toBeTrue();

    $limit = GenerationLimit::query()->where('workspace_id', $this->workspace->id)->sole();

    expect($limit->generations_used)->toBe(1)
        ->and($limit->period_starts_at->toDateTimeString())->toBe('2026-06-01 00:00:00');
});

it('never passes the cap, however the slots are asked for', function (): void {
    GenerationLimit::factory()->forWorkspace($this->workspace)->create(['monthly_limit' => 5, 'generations_used' => 3]);

    $granted = collect(range(1, 6))->filter(fn (): bool => app(GenerationAllowance::class)->reserve($this->workspace->id))->count();

    expect($granted)->toBe(2)
        ->and(gaUsed($this->workspace->id))->toBe(5);
});

it('gives back the slot of a failed file', function (): void {
    GenerationLimit::factory()->forWorkspace($this->workspace)->create(['monthly_limit' => 2, 'generations_used' => 2]);

    $this->allowance->release($this->workspace->id, now()->subMinute());

    expect(gaUsed($this->workspace->id))->toBe(1)
        ->and($this->allowance->reserve($this->workspace->id))->toBeTrue();
});

it('does not give back a slot of an earlier month, nor go below zero', function (): void {
    GenerationLimit::factory()->forWorkspace($this->workspace)->create(['generations_used' => 1]);

    $this->allowance->release($this->workspace->id, Carbon::parse('2026-05-31 23:59:00'));
    expect(gaUsed($this->workspace->id))->toBe(1);

    $this->allowance->release($this->workspace->id, now());
    $this->allowance->release($this->workspace->id, now());
    expect(gaUsed($this->workspace->id))->toBe(0);
});
