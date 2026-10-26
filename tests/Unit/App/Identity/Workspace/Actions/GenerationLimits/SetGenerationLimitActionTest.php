<?php

use App\Identity\Workspace\Actions\GenerationLimits\SetGenerationLimitAction;
use App\Identity\Workspace\Models\GenerationLimit;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Carbon;

/**
 * Setting a generation limit creates or updates the workspace's one row and
 * keeps this month's count; null removes the row (no cap). A row left from
 * an earlier month starts this month again at zero.
 */
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));
    $this->workspace = User::factory()->create()->workspace();
    $this->action = app(SetGenerationLimitAction::class);
});

it('creates a limit that starts this month at zero', function (): void {
    $this->action->execute($this->workspace, 40);

    $limit = GenerationLimit::query()->where('workspace_id', $this->workspace->id)->sole();

    expect($limit->monthly_limit)->toBe(40)
        ->and($limit->generations_used)->toBe(0)
        ->and($limit->period_starts_at->toDateTimeString())->toBe('2026-06-01 00:00:00');
});

it('keeps what was made this month when the limit changes', function (): void {
    GenerationLimit::factory()->forWorkspace($this->workspace)->create(['monthly_limit' => 40, 'generations_used' => 25]);

    $this->action->execute($this->workspace, 20);

    $limit = GenerationLimit::query()->where('workspace_id', $this->workspace->id)->sole();

    expect($limit->monthly_limit)->toBe(20)
        ->and($limit->generations_used)->toBe(25)
        ->and($limit->reached())->toBeTrue();
});

it('starts a row of an earlier month again at zero', function (): void {
    GenerationLimit::factory()->forWorkspace($this->workspace)->lastMonth()->create(['generations_used' => 30]);

    $this->action->execute($this->workspace, 60);

    $limit = GenerationLimit::query()->where('workspace_id', $this->workspace->id)->sole();

    expect($limit->generations_used)->toBe(0)
        ->and($limit->period_starts_at->toDateTimeString())->toBe('2026-06-01 00:00:00');
});

it('removes the limit for null', function (): void {
    GenerationLimit::factory()->forWorkspace($this->workspace)->create();

    $this->action->execute($this->workspace, null);

    expect(GenerationLimit::query()->where('workspace_id', $this->workspace->id)->exists())->toBeFalse();
});
