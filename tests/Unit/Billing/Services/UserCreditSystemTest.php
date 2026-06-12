<?php

use App\Identity\Workspace\Models\User;
use Domain\Billing\Models\UserAddon;
use Domain\Billing\Models\UserCredit;
use Illuminate\Support\Facades\Cache;

beforeEach(fn () => Cache::flush());

it('returns 0 when user has no credit row', function (): void {
    $user = User::factory()->create();

    $available = $user->creditSystem()->consumableMessages(refresh: true);

    expect($available)->toBe(0);
});

it('returns 0 when the only credit is expired', function (): void {
    $user = User::factory()->create();

    UserCredit::factory()->create([
        'user_id' => $user->id,
        'messages' => 100,
        'started_at' => now()->subDays(40),
        'ends_at' => now()->subDay(),
    ]);

    $available = $user->creditSystem()->consumableMessages(refresh: true);

    expect($available)->toBe(0)
        ->and($user->credit)->toBeNull();
});

it('returns limit minus usage when active credit has consumed messages', function (): void {
    $user = User::factory()->create();

    $credit = UserCredit::factory()->create([
        'user_id' => $user->id,
        'messages' => 100,
        'started_at' => now()->subDays(5),
        'ends_at' => now()->addDays(25),
    ]);

    $credit->usage()->create(['messages' => 30]);

    $available = $user->creditSystem()->consumableMessages(refresh: true);

    expect($available)->toBe(70);
});

it('clamps at 0 when usage equals or exceeds limit', function (): void {
    $user = User::factory()->create();

    $credit = UserCredit::factory()->create([
        'user_id' => $user->id,
        'messages' => 100,
        'started_at' => now()->subDays(5),
        'ends_at' => now()->addDays(25),
    ]);

    $credit->usage()->create(['messages' => 150]);

    $available = $user->creditSystem()->consumableMessages(refresh: true);

    expect($available)->toBe(0);
});

it('adds active addons on top of remaining credit', function (): void {
    $user = User::factory()->create();

    $credit = UserCredit::factory()->create([
        'user_id' => $user->id,
        'messages' => 100,
        'started_at' => now()->subDays(5),
        'ends_at' => now()->addDays(25),
    ]);
    $credit->usage()->create(['messages' => 40]);

    UserAddon::factory()->create([
        'user_id' => $user->id,
        'type' => 'extra_message_credits',
        'amount' => 500,
        'started_at' => now(),
        'ends_at' => now()->addMonth(),
    ]);

    $available = $user->creditSystem()->consumableMessages(refresh: true);

    expect($available)->toBe(560);
});

it('ignores expired addons', function (): void {
    $user = User::factory()->create();

    $credit = UserCredit::factory()->create([
        'user_id' => $user->id,
        'messages' => 100,
        'started_at' => now()->subDays(5),
        'ends_at' => now()->addDays(25),
    ]);
    $credit->usage()->create(['messages' => 10]);

    UserAddon::factory()->create([
        'user_id' => $user->id,
        'type' => 'extra_message_credits',
        'amount' => 500,
        'started_at' => now()->subMonths(2),
        'ends_at' => now()->subDay(),
    ]);

    $available = $user->creditSystem()->consumableMessages(refresh: true);

    expect($available)->toBe(90);
});

it('sums multiple active addons', function (): void {
    $user = User::factory()->create();

    $credit = UserCredit::factory()->create([
        'user_id' => $user->id,
        'messages' => 100,
        'started_at' => now()->subDays(5),
        'ends_at' => now()->addDays(25),
    ]);
    $credit->usage()->create(['messages' => 0]);

    UserAddon::factory()->count(3)->create([
        'user_id' => $user->id,
        'type' => 'extra_message_credits',
        'amount' => 1000,
        'started_at' => now(),
        'ends_at' => now()->addMonth(),
    ]);

    $available = $user->creditSystem()->consumableMessages(refresh: true);

    expect($available)->toBe(3100);
});

it('returns the active credit and ignores expired ones via the credit relation', function (): void {
    $user = User::factory()->create();

    UserCredit::factory()->create([
        'user_id' => $user->id,
        'messages' => 50,
        'started_at' => now()->subDays(40),
        'ends_at' => now()->subDays(10),
    ]);

    $active = UserCredit::factory()->create([
        'user_id' => $user->id,
        'messages' => 200,
        'started_at' => now()->subDays(2),
        'ends_at' => now()->addDays(28),
    ]);

    $user->refresh();

    expect($user->credit)->not->toBeNull()
        ->and($user->credit->id)->toBe($active->id);
});

// --- canSendMessages: the GATE (separate cache key) vs the calculation -------
//
// The tests above all exercise consumableMessages() — the pure remaining-quota
// calculation. canSendMessages() is the method that actually gates every chat
// turn, and it reads a SEPARATE cached flag that can drift from that
// calculation. These cover the gate itself.

it('canSendMessages returns false and caches the gate flag when there is no active credit', function (): void {
    $user = User::factory()->create();

    $system = $user->creditSystem();

    expect($system->canSendMessages())->toBeFalse()
        // the gate is cached (not recomputed every call) — this is the separate
        // key that can disagree with the live consumableMessages calculation.
        ->and(Cache::get('user:'.$user->id.':can-send-messages'))->toBeFalse();
});

it('canSendMessages returns true when an active credit has remaining messages', function (): void {
    $user = User::factory()->create();

    UserCredit::factory()->create([
        'user_id' => $user->id,
        'messages' => 100,
        'started_at' => now()->subDay(),
        'ends_at' => now()->addDays(20),
    ]);

    expect($user->creditSystem()->canSendMessages())->toBeTrue();
});

it('refreshCanSendMessages flips the gate to false once usage exhausts the active credit', function (): void {
    $user = User::factory()->create();

    $credit = UserCredit::factory()->create([
        'user_id' => $user->id,
        'messages' => 100,
        'started_at' => now()->subDay(),
        'ends_at' => now()->addDays(20),
    ]);
    $credit->usage()->create(['messages' => 100]); // fully consumed → 0 available

    $system = $user->creditSystem();

    expect($system->refreshCanSendMessages())->toBeFalse()
        ->and($system->canSendMessages())->toBeFalse()
        ->and(Cache::get('user:'.$user->id.':can-send-messages'))->toBeFalse();
});

it('trusts the cached gate even when it disagrees with live availability, until refreshed', function (): void {
    $user = User::factory()->create();

    $credit = UserCredit::factory()->create([
        'user_id' => $user->id,
        'messages' => 100,
        'started_at' => now()->subDay(),
        'ends_at' => now()->addDays(20),
    ]);

    $system = $user->creditSystem();

    // A prior healthy turn primed the gate to "true".
    $system->setCanSendMessages(true);

    // The credit is then exhausted at the source of truth (DB usage).
    $credit->usage()->create(['messages' => 100]);

    // The live calculation already reflects 0 remaining…
    expect($system->consumableMessages(refresh: true))->toBe(0)
        // …yet the gate still trusts its cached "true" — the exact staleness the
        // send path must reconcile (canSendMessages can disagree with consumableMessages).
        ->and($system->canSendMessages())->toBeTrue();

    // Reconciling from the database flips the gate closed.
    expect($system->refreshCanSendMessages())->toBeFalse()
        ->and($system->canSendMessages())->toBeFalse();
});

// --- sent-messages counter: cache-first with a DB fallback -------------------
//
// The counter is incremented in lockstep with user_usages.messages by
// ConsumeMessageCreditsAction; the DB row is the rebuild source when the cache
// key is lost mid-cycle (flush/expiry).

it('getTotalSentMessages falls back to the DB usage row on a cache miss and re-caches it', function (): void {
    $user = User::factory()->create();

    $credit = UserCredit::factory()->create([
        'user_id' => $user->id,
        'messages' => 100,
        'started_at' => now()->subDays(5),
        'ends_at' => now()->addDays(25),
    ]);
    $credit->usage()->create(['messages' => 30]);

    // Cache is empty (flushed in beforeEach) — without the DB fallback this
    // showed 0 sent on the usage dashboard until the next billing cycle.
    expect($user->creditSystem()->getTotalSentMessages())->toBe(30)
        ->and(Cache::get('user:'.$user->id.':credit:messages:sent'))->toBe(30);
});

it('getTotalSentMessages prefers the cached counter over the DB row', function (): void {
    $user = User::factory()->create();

    $credit = UserCredit::factory()->create([
        'user_id' => $user->id,
        'messages' => 100,
        'started_at' => now()->subDays(5),
        'ends_at' => now()->addDays(25),
    ]);
    $credit->usage()->create(['messages' => 30]);

    // Steady state: consumption jobs increment the cache directly; reads must
    // not bypass it for a DB query.
    Cache::put('user:'.$user->id.':credit:messages:sent', 7, now()->addDay());

    expect($user->creditSystem()->getTotalSentMessages())->toBe(7);
});

it('getTotalSentMessages returns 0 when there is no active credit', function (): void {
    $user = User::factory()->create();

    expect($user->creditSystem()->getTotalSentMessages())->toBe(0);
});

// --- tokens: ANALYTICS-only accumulator (tokens never gate or bill) ----------

it('recordTokenUsage accumulates consumed tokens on the usage row (analytics only)', function (): void {
    $user = User::factory()->create();

    $credit = UserCredit::factory()->create([
        'user_id' => $user->id,
        'started_at' => now()->subDay(),
        'ends_at' => now()->addDays(20),
    ]);
    $credit->usage()->create(['tokens' => 0]);

    $system = $user->creditSystem();
    $system->recordTokenUsage(400_000);
    $system->recordTokenUsage(150_000);

    // Tokens are recorded for insight only — they do NOT affect the send gate
    // (which is messages); this is the "tokens consumed this cycle" figure.
    expect($credit->usage()->first()->tokens)->toBe(550_000);
});

it('addCredit grants the plan message allowance for the cycle', function (): void {
    $user = User::factory()->create();

    $credit = $user->creditSystem()->addCredit(['messages_limit' => 4000], now()->addMonth());

    expect($credit->messages)->toBe(4000);
});
