<?php

use App\Identity\Workspace\Models\MessageLimit;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\MessageAllowance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * MessageAllowance answers "may this account send one more message this
 * month?" from its MessageLimit, cached per account and month.
 */
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));
    $this->workspace = User::factory()->workspace()->create();
    $this->allowance = app(MessageAllowance::class);
});

it('lets an account without a limit send', function (): void {
    expect($this->allowance->canSend($this->workspace->id))->toBeTrue();
});

it('refuses an account that used its whole limit this month', function (): void {
    MessageLimit::create(['user_id' => $this->workspace->id, 'monthly_limit' => 5, 'messages_used' => 5, 'period_starts_at' => '2026-06-01']);

    expect($this->allowance->canSend($this->workspace->id))->toBeFalse();
});

it('lets the account send again when a new month starts', function (): void {
    MessageLimit::create(['user_id' => $this->workspace->id, 'monthly_limit' => 5, 'messages_used' => 5, 'period_starts_at' => '2026-06-01']);

    expect($this->allowance->canSend($this->workspace->id))->toBeFalse();

    // The cached answer belongs to June, so July reads the row again.
    $this->travelTo(Carbon::parse('2026-07-01 00:05:00'));

    expect($this->allowance->canSend($this->workspace->id))->toBeTrue();
});

it('answers from the cache without a query once warmed', function (): void {
    MessageLimit::create(['user_id' => $this->workspace->id, 'monthly_limit' => 5, 'messages_used' => 1, 'period_starts_at' => '2026-06-01']);
    $this->allowance->canSend($this->workspace->id);

    DB::enableQueryLog();
    $this->allowance->canSend($this->workspace->id);

    expect(collect(DB::getQueryLog())->filter(fn (array $q): bool => str_contains($q['query'], 'message_limits')))->toBeEmpty();
});
