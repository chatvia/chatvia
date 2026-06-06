<?php

use App\Identity\Workspace\Models\User;
use Domain\Billing\Models\UserCredit;
use Domain\Billing\Services\ChatterMessageAllowance;

beforeEach(function (): void {
    $this->workspace = User::factory()->workspace()->create();
    $this->chatter = User::factory()->chatter($this->workspace)->create();
    $this->allowance = app(ChatterMessageAllowance::class);
});

it('treats a chatter with no credit row as unlimited', function (): void {
    expect($this->allowance->currentLimit($this->chatter))->toBeNull()
        ->and($this->allowance->usedThisPeriod($this->chatter))->toBe(0)
        ->and($this->allowance->limitReached($this->chatter))->toBeFalse();
});

it('sets a monthly cap by creating a current-period credit + usage', function (): void {
    $this->allowance->setLimit($this->chatter, 100);

    expect($this->allowance->currentLimit($this->chatter))->toBe(100)
        ->and($this->allowance->usedThisPeriod($this->chatter))->toBe(0)
        ->and($this->allowance->limitReached($this->chatter))->toBeFalse();

    $credit = UserCredit::query()->where('user_id', $this->chatter->id)->first();
    expect($credit)->not->toBeNull()
        ->and($credit->messages)->toBe(100)
        ->and($credit->usage)->not->toBeNull();
});

it('counts messages and reports the limit reached at exactly the cap', function (): void {
    $this->allowance->setLimit($this->chatter, 2);

    $this->allowance->recordMessage($this->chatter);
    expect($this->allowance->usedThisPeriod($this->chatter))->toBe(1)
        ->and($this->allowance->limitReached($this->chatter))->toBeFalse();

    $this->allowance->recordMessage($this->chatter);
    expect($this->allowance->usedThisPeriod($this->chatter))->toBe(2)
        ->and($this->allowance->limitReached($this->chatter))->toBeTrue();
});

it('clears the limit by deleting the chatter credit rows', function (): void {
    $this->allowance->setLimit($this->chatter, 50);
    $this->allowance->setLimit($this->chatter, null);

    expect($this->allowance->currentLimit($this->chatter))->toBeNull()
        ->and(UserCredit::query()->where('user_id', $this->chatter->id)->exists())->toBeFalse();
});

it('preserves usage when the cap is raised mid-month', function (): void {
    $this->allowance->setLimit($this->chatter, 5);
    $this->allowance->recordMessage($this->chatter);
    $this->allowance->recordMessage($this->chatter);

    $this->allowance->setLimit($this->chatter, 1000);

    expect($this->allowance->currentLimit($this->chatter))->toBe(1000)
        ->and($this->allowance->usedThisPeriod($this->chatter))->toBe(2);
});

it('renews the monthly window lazily, carrying the cap forward', function (): void {
    // Last month's exhausted credit.
    $credit = UserCredit::query()->create([
        'user_id' => $this->chatter->id,
        'messages' => 5,
        'started_at' => now()->subMonthNoOverflow()->startOfMonth(),
        'ends_at' => now()->subMonthNoOverflow()->endOfMonth(),
    ]);
    $credit->usage()->create(['messages' => 5]);

    // A send in the new month provisions a fresh row carrying the cap forward.
    $this->allowance->recordMessage($this->chatter);

    expect($this->allowance->currentLimit($this->chatter))->toBe(5)
        ->and($this->allowance->usedThisPeriod($this->chatter))->toBe(1)
        ->and($this->allowance->limitReached($this->chatter))->toBeFalse();
});

it('caches the can-send gate and re-caches it when the cap changes via the members page', function (): void {
    $this->allowance->setLimit($this->chatter, 1);
    $this->allowance->recordMessage($this->chatter);                 // exhaust the cap

    expect($this->allowance->canSendMessages($this->chatter))->toBeFalse(); // blocked → caches "no"

    $this->allowance->setLimit($this->chatter, 10);                  // admin raises the cap

    // Must reflect the new cap immediately, not serve the stale "blocked" flag.
    expect($this->allowance->canSendMessages($this->chatter))->toBeTrue();
});

it('re-caches the gate when a new month rolls over, even if last month was blocked', function (): void {
    $this->allowance->setLimit($this->chatter, 2);
    $this->allowance->recordMessage($this->chatter);
    $this->allowance->recordMessage($this->chatter);
    expect($this->allowance->canSendMessages($this->chatter))->toBeFalse(); // exhausted → caches "no"

    // The month actually ends (time auto-resets after the test).
    $this->travelTo(now()->addMonthNoOverflow()->startOfMonth()->addDays(2));

    // First send of the new month provisions a fresh window (cap carried forward);
    // the gate must reset to "can send", not serve last month's stale flag.
    $this->allowance->recordMessage($this->chatter);

    expect($this->allowance->canSendMessages($this->chatter))->toBeTrue()
        ->and($this->allowance->usedThisPeriod($this->chatter))->toBe(1);
});

it('exposes an id-based gate: unknown id is allowed, real chatter mirrors the can-send check', function (): void {
    expect($this->allowance->canSendMessagesById(999_999))->toBeTrue(); // unknown user → not gated

    $this->allowance->setLimit($this->chatter, 1);
    $this->allowance->recordMessage($this->chatter);                    // exhaust the cap

    expect($this->allowance->canSendMessagesById($this->chatter->id))->toBeFalse()
        ->and($this->allowance->canSendMessagesById($this->chatter->id))
            ->toBe($this->allowance->canSendMessages($this->chatter));
});

it('is a no-op for non-chatter users so billing credits are never touched', function (): void {
    // The workspace owner has their own billing user_credits; the chatter
    // allowance must never read or write them.
    $this->allowance->setLimit($this->workspace, 10);
    $this->allowance->recordMessage($this->workspace);

    expect($this->allowance->currentLimit($this->workspace))->toBeNull()
        ->and($this->allowance->limitReached($this->workspace))->toBeFalse()
        ->and(UserCredit::query()->where('user_id', $this->workspace->id)->exists())->toBeFalse();
});
