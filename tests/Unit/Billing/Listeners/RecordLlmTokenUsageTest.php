<?php

use App\Identity\Workspace\Models\User;
use Domain\Billing\Listeners\RecordLlmTokenUsage;
use Domain\Billing\Models\UserCredit;
use Domain\Llm\Events\LlmTokensConsumed;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Redis;

/**
 * RecordLlmTokenUsage buffers an LlmTokensConsumed event in Redis (the
 * listener fires inline on the request path for every LLM op, so it must not
 * touch the DB); app:flush-token-usage-buffer drains the buffer into the
 * workspace's active billing-cycle usage `tokens` accumulator once a minute
 * (analytics only — tokens never gate or bill).
 */
beforeEach(function (): void {
    Redis::del(RecordLlmTokenUsage::BUFFER_KEY);
});

afterEach(function (): void {
    Redis::del(RecordLlmTokenUsage::BUFFER_KEY);
});

it('buffers token counts in Redis instead of writing the DB inline', function (): void {
    $user = User::factory()->create();

    app(RecordLlmTokenUsage::class)->handle(new LlmTokensConsumed($user->id, 250, 'chat'));
    app(RecordLlmTokenUsage::class)->handle(new LlmTokensConsumed($user->id, 50, 'rerank'));

    expect((int) Redis::hget(RecordLlmTokenUsage::BUFFER_KEY, (string) $user->id))->toBe(300);
});

it('flushes the buffer into the active billing cycle usage tokens', function (): void {
    $user = User::factory()->create();
    $credit = UserCredit::factory()->create([
        'user_id' => $user->id,
        'ends_at' => now()->addDays(10),
    ]);
    // Seed the starting total the way production does — via increment, not mass
    // assignment (tokens is written only through RecordTokenUsageAction).
    $credit->usage()->create()->increment('tokens', 1000);

    app(RecordLlmTokenUsage::class)->handle(new LlmTokensConsumed($user->id, 250, 'chat'));

    Artisan::call('app:flush-token-usage-buffer');

    // phpredis returns false for a missing hash field.
    expect($credit->usage()->first()->tokens)->toBe(1250)
        ->and(Redis::hget(RecordLlmTokenUsage::BUFFER_KEY, (string) $user->id))->toBeFalsy();
});

it('is wired: dispatching the event buffers via the registered listener', function (): void {
    $user = User::factory()->create();

    // Real dispatch through the app dispatcher — proves the Event::listen
    // registration in DomainServiceProvider actually fires the listener.
    event(new LlmTokensConsumed($user->id, 77));

    expect((int) Redis::hget(RecordLlmTokenUsage::BUFFER_KEY, (string) $user->id))->toBe(77);
});
