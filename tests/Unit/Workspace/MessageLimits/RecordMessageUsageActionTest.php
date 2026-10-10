<?php

use App\Identity\Workspace\Actions\MessageLimits\RecordMessageUsageAction;
use App\Identity\Workspace\Models\MessageLimit;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\MessageAllowance;
use Illuminate\Support\Carbon;

/**
 * Each counted message adds one to this month's count. A new month restarts
 * the count on its first message, and the message that reaches the limit
 * drops the cached "may send" answer so the next turn is refused.
 */
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));
    $this->workspace = User::factory()->workspace()->create();
    $this->action = app(RecordMessageUsageAction::class);
});

it('does nothing for an account without a limit', function (): void {
    $this->action->execute($this->workspace->id);

    expect(MessageLimit::query()->where('user_id', $this->workspace->id)->exists())->toBeFalse();
});

it('counts a message in the current month', function (): void {
    MessageLimit::create(['user_id' => $this->workspace->id, 'monthly_limit' => 10, 'messages_used' => 3, 'period_starts_at' => '2026-06-01']);

    $this->action->execute($this->workspace->id);

    expect(MessageLimit::query()->where('user_id', $this->workspace->id)->value('messages_used'))->toBe(4);
});

it('restarts the count on the first message of a new month', function (): void {
    MessageLimit::create(['user_id' => $this->workspace->id, 'monthly_limit' => 10, 'messages_used' => 10, 'period_starts_at' => '2026-05-01']);

    $this->action->execute($this->workspace->id);

    $limit = MessageLimit::query()->where('user_id', $this->workspace->id)->sole();

    expect($limit->messages_used)->toBe(1)
        ->and($limit->period_starts_at->toDateTimeString())->toBe('2026-06-01 00:00:00');
});

it('refuses the next turn once the message reaching the limit is counted', function (): void {
    MessageLimit::create(['user_id' => $this->workspace->id, 'monthly_limit' => 2, 'messages_used' => 1, 'period_starts_at' => '2026-06-01']);
    $allowance = app(MessageAllowance::class);

    // Warm the cached answer while there is still room.
    expect($allowance->canSend($this->workspace->id))->toBeTrue();

    $this->action->execute($this->workspace->id);

    expect($allowance->canSend($this->workspace->id))->toBeFalse();
});
