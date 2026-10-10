<?php

use App\Identity\Workspace\Actions\MessageLimits\SetMessageLimitAction;
use App\Identity\Workspace\Models\MessageLimit;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\MessageAllowance;
use Illuminate\Support\Carbon;

/**
 * Setting a limit creates or updates the account's one row and keeps this
 * month's count; null removes the row (no limit). Every change drops the
 * cached "may send" answer, so it applies on the next turn.
 */
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));
    $this->workspace = User::factory()->workspace()->create();
    $this->action = app(SetMessageLimitAction::class);
});

it('creates a limit that starts this month at zero', function (): void {
    $this->action->execute($this->workspace, 300);

    $limit = MessageLimit::query()->where('user_id', $this->workspace->id)->sole();

    expect($limit->monthly_limit)->toBe(300)
        ->and($limit->messages_used)->toBe(0)
        ->and($limit->period_starts_at->toDateTimeString())->toBe('2026-06-01 00:00:00');
});

it('keeps what was used this month when the limit changes', function (): void {
    MessageLimit::create(['user_id' => $this->workspace->id, 'monthly_limit' => 300, 'messages_used' => 120, 'period_starts_at' => '2026-06-01']);

    $this->action->execute($this->workspace, 100);

    $limit = MessageLimit::query()->where('user_id', $this->workspace->id)->sole();

    expect($limit->monthly_limit)->toBe(100)
        ->and($limit->messages_used)->toBe(120)
        ->and($limit->reached())->toBeTrue();
});

it('restarts the count when the stored month is over', function (): void {
    MessageLimit::create(['user_id' => $this->workspace->id, 'monthly_limit' => 300, 'messages_used' => 280, 'period_starts_at' => '2026-05-01']);

    $this->action->execute($this->workspace, 500);

    $limit = MessageLimit::query()->where('user_id', $this->workspace->id)->sole();

    expect($limit->messages_used)->toBe(0)
        ->and($limit->period_starts_at->toDateTimeString())->toBe('2026-06-01 00:00:00');
});

it('removes the limit when null is given', function (): void {
    $this->action->execute($this->workspace, 300);
    $this->action->execute($this->workspace, null);

    expect(MessageLimit::query()->where('user_id', $this->workspace->id)->exists())->toBeFalse();
});

it('applies a lower limit to the cached answer right away', function (): void {
    MessageLimit::create(['user_id' => $this->workspace->id, 'monthly_limit' => 300, 'messages_used' => 50, 'period_starts_at' => '2026-06-01']);
    $allowance = app(MessageAllowance::class);

    expect($allowance->canSend($this->workspace->id))->toBeTrue();

    $this->action->execute($this->workspace, 50);

    expect($allowance->canSend($this->workspace->id))->toBeFalse();
});
