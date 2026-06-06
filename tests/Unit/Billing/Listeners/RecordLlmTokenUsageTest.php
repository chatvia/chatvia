<?php

use App\Identity\Workspace\Models\User;
use Domain\Billing\Listeners\RecordLlmTokenUsage;
use Domain\Billing\Models\UserCredit;
use Domain\Llm\Events\LlmTokensConsumed;

/**
 * RecordLlmTokenUsage turns an LlmTokensConsumed event into an increment on the
 * workspace's active billing-cycle usage `tokens` accumulator (analytics only —
 * tokens never gate or bill).
 */
it('increments the active billing cycle usage tokens for the workspace', function (): void {
    $user = User::factory()->create();
    $credit = UserCredit::factory()->create([
        'user_id' => $user->id,
        'ends_at' => now()->addDays(10),
    ]);
    // Seed the starting total the way production does — via increment, not mass
    // assignment (tokens is written only through UserCreditSystem::recordTokenUsage).
    $credit->usage()->create()->increment('tokens', 1000);

    app(RecordLlmTokenUsage::class)->handle(new LlmTokensConsumed($user->id, 250, 'chat'));

    expect($credit->usage()->first()->tokens)->toBe(1250);
});

it('is a no-op (does not throw) when the workspace has no active credit', function (): void {
    $user = User::factory()->create();

    app(RecordLlmTokenUsage::class)->handle(new LlmTokensConsumed($user->id, 250));

    expect($user->credits()->count())->toBe(0);
});

it('is wired: dispatching the event records via the registered listener', function (): void {
    $user = User::factory()->create();
    $credit = UserCredit::factory()->create([
        'user_id' => $user->id,
        'ends_at' => now()->addDays(10),
    ]);
    $credit->usage()->create();

    // Real dispatch through the app dispatcher — proves the Event::listen
    // registration in DomainServiceProvider actually fires the listener.
    event(new LlmTokensConsumed($user->id, 77));

    expect($credit->usage()->first()->tokens)->toBe(77);
});
