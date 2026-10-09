<?php

use App\Identity\Workspace\Enums\UserType;
use App\Identity\Workspace\Models\User;
use Domain\Billing\Models\UserAddon;
use Domain\Billing\Models\UserCredit;

/**
 * MessageCredits is the extracted single source of truth for cycle-usage +
 * addon math previously inlined twice in GetDashboardStatsAction. These tests
 * pin the four invariants worth preserving:
 *   1. without a UserCredit row, every field collapses to a sensible zero/null,
 *   2. base quota comes from UserCredit.messages and `used` from the billing
 *      counter (user_usages.messages) — the same source the Usage page reads,
 *      so billed-but-unpersisted replies (eval cases) count too,
 *   3. addons are SCOPED by type + expiry,
 *   4. available() = max(0, base - used) + addons — the "addons survive
 *      base-quota overrun" semantic that matches UserCreditSystem's behaviour.
 *
 * Routing through User::workspace() is also pinned so an employee account
 * sees the workspace owner's credits.
 */

/**
 * Record `$messages` billed credits on the cycle's usage row — what
 * ConsumeMessageCreditsAction does for every billed reply (chat, channel,
 * eval case).
 */
function billCycleUsage(UserCredit $credit, int $messages): void
{
    $credit->usage()->firstOrCreate([])->increment('messages', $messages);
}

describe('without a UserCredit row', function (): void {
    it('returns 0 / 0 / 0 / null across the surface', function (): void {
        $user = User::factory()->create();
        $credits = $user->messageCredits();

        expect($credits->used())->toBe(0)
            ->and($credits->baseLimit())->toBe(0)
            ->and($credits->addons())->toBe(0)
            ->and($credits->available())->toBe(0)
            ->and($credits->limit())->toBe(0)
            ->and($credits->cycleBounds())->toBeNull();
    });
});

describe('with a UserCredit row but no usage', function (): void {
    it('reports base quota from credit.messages and zero usage', function (): void {
        $user = User::factory()->create();
        UserCredit::create([
            'user_id' => $user->id,
            'messages' => 500,
            'started_at' => now()->subDays(3),
            'ends_at' => now()->addDays(27),
        ]);
        $user->refresh();

        $credits = $user->messageCredits();

        expect($credits->used())->toBe(0)
            ->and($credits->baseLimit())->toBe(500)
            ->and($credits->available())->toBe(500)
            ->and($credits->limit())->toBe(500);

        $bounds = $credits->cycleBounds();
        expect($bounds)->toBeArray()
            ->and($bounds)->toHaveKeys(['started_at', 'ends_at']);
    });
});

describe('used()', function (): void {
    it('reads the billing counter (user_usages.messages) for the active cycle', function (): void {
        $user = User::factory()->create();
        $credit = UserCredit::create([
            'user_id' => $user->id,
            'messages' => 100,
            'started_at' => now()->subDays(2),
            'ends_at' => now()->addDays(28),
        ]);
        $user->refresh();

        billCycleUsage($credit, 12);
        billCycleUsage($credit, 8);

        expect($user->messageCredits()->used())->toBe(20);
    });

    it('ignores usage rows from expired credit cycles', function (): void {
        $user = User::factory()->create();

        $expired = UserCredit::create([
            'user_id' => $user->id,
            'messages' => 100,
            'started_at' => now()->subMonths(2),
            'ends_at' => now()->subMonth(),
        ]);
        billCycleUsage($expired, 99); // last cycle — must not count

        $active = UserCredit::create([
            'user_id' => $user->id,
            'messages' => 100,
            'started_at' => now()->subDay(),
            'ends_at' => now()->addDays(29),
        ]);
        billCycleUsage($active, 7);
        $user->refresh();

        expect($user->messageCredits()->used())->toBe(7);
    });
});

describe('addons()', function (): void {
    it('sums only type=extra_message_credits addons that have not expired', function (): void {
        $user = User::factory()->create();

        // counted
        UserAddon::create(['user_id' => $user->id, 'type' => 'extra_message_credits', 'amount' => 50, 'ends_at' => now()->addDay()]);
        UserAddon::create(['user_id' => $user->id, 'type' => 'extra_message_credits', 'amount' => 30, 'ends_at' => now()->addMonth()]);

        // not counted — wrong type
        UserAddon::create(['user_id' => $user->id, 'type' => 'extra_agents', 'amount' => 9999, 'ends_at' => now()->addYear()]);

        // not counted — expired
        UserAddon::create(['user_id' => $user->id, 'type' => 'extra_message_credits', 'amount' => 1000, 'ends_at' => now()->subDay()]);

        expect($user->messageCredits()->addons())->toBe(80);
    });
});

describe('available() math', function (): void {
    it('available = max(0, base - used) + addons — addons survive base-quota overrun', function (): void {
        $user = User::factory()->create();
        $credit = UserCredit::create([
            'user_id' => $user->id,
            'messages' => 10,
            'started_at' => now()->subDay(),
            'ends_at' => now()->addDays(29),
        ]);
        UserAddon::create(['user_id' => $user->id, 'type' => 'extra_message_credits', 'amount' => 5, 'ends_at' => now()->addMonth()]);
        $user->refresh();

        billCycleUsage($credit, 20); // 20 used vs 10 base → base overrun

        $credits = $user->messageCredits();

        expect($credits->used())->toBe(20)
            ->and($credits->baseLimit())->toBe(10)
            ->and($credits->addons())->toBe(5)
            // max(0, 10 - 20) + 5 = 0 + 5 = 5  ← the load-bearing invariant
            ->and($credits->available())->toBe(5)
            // 10 + 5
            ->and($credits->limit())->toBe(15);
    });

    it('available = remaining base + addons when usage is below base', function (): void {
        $user = User::factory()->create();
        $credit = UserCredit::create([
            'user_id' => $user->id,
            'messages' => 100,
            'started_at' => now()->subDay(),
            'ends_at' => now()->addDays(29),
        ]);
        UserAddon::create(['user_id' => $user->id, 'type' => 'extra_message_credits', 'amount' => 40, 'ends_at' => now()->addMonth()]);
        $user->refresh();

        billCycleUsage($credit, 30);

        // (100 - 30) + 40 = 110
        expect($user->messageCredits()->available())->toBe(110);
    });
});

describe('workspace routing', function (): void {
    it('an employee\'s messageCredits() reads the workspace owner\'s billing', function (): void {
        $owner = User::factory()->create();
        UserCredit::create([
            'user_id' => $owner->id,
            'messages' => 250,
            'started_at' => now()->subDay(),
            'ends_at' => now()->addDays(29),
        ]);
        $owner->refresh();

        $employee = User::factory()->create([
            'type' => UserType::Employee,
            'workspace_id' => $owner->id,
        ]);

        expect($employee->messageCredits()->baseLimit())->toBe(250)
            ->and($employee->messageCredits()->limit())->toBe(250);
    });
});
