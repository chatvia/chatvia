<?php

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\MessageCredits;
use Domain\Billing\Models\UserAddon;
use Domain\Billing\Models\UserCredit;
use Illuminate\Support\Facades\DB;
use Support\Enums\User\UserType;

/**
 * MessageCredits is the extracted single source of truth for cycle-usage +
 * addon math previously inlined twice in GetDashboardStatsAction. These tests
 * pin the four invariants worth preserving:
 *   1. without a UserCredit row, every field collapses to a sensible zero/null,
 *   2. base quota comes from UserCredit.messages,
 *   3. addons are SCOPED by type + expiry,
 *   4. available() = max(0, base - used) + addons — the "addons survive
 *      base-quota overrun" semantic that matches UserCreditSystem's behaviour.
 *
 * Routing through User::workspace() is also pinned so an employee account
 * sees the workspace owner's credits.
 */

/**
 * Insert a rollup row tied to this workspace id, simulating `$amount` assistant
 * messages. The PK is (agent_id, bucket_hour, country_code, llm_external_name)
 * so each call uses a distinct synthetic agent_id to avoid PK collisions.
 */
function insertRollupUsage(int $workspaceId, int $assistantMessages, ?Carbon\Carbon $bucketHour = null): void
{
    static $syntheticAgentId = 1_000_000_000;

    DB::connection(config('chatvia.analytics.connection'))
        ->table('analytics_agent_hourly_rollups')
        ->insert([
            'agent_id' => $syntheticAgentId++,
            'workspace_id' => $workspaceId,
            'bucket_hour' => $bucketHour ?? now()->subHour(),
            'country_code' => 'US',
            'llm_external_name' => 'test-model',
            'conversations_started' => 0,
            'user_messages' => 0,
            'assistant_messages' => $assistantMessages,
            'total_tokens' => 0,
            'response_ms_sum' => 0,
            'response_ms_count' => 0,
        ]);
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
    it('sums assistant_messages from rollup rows inside the credit cycle', function (): void {
        $user = User::factory()->create();
        UserCredit::create([
            'user_id' => $user->id,
            'messages' => 100,
            'started_at' => now()->subDays(2),
            'ends_at' => now()->addDays(28),
        ]);
        $user->refresh();

        insertRollupUsage($user->id, 12);
        insertRollupUsage($user->id, 8);

        expect($user->messageCredits()->used())->toBe(20);
    });

    it('ignores rollup rows from BEFORE the cycle started', function (): void {
        $user = User::factory()->create();
        UserCredit::create([
            'user_id' => $user->id,
            'messages' => 100,
            'started_at' => now()->subDay(),
            'ends_at' => now()->addDays(29),
        ]);
        $user->refresh();

        insertRollupUsage($user->id, 7);                                       // inside cycle
        insertRollupUsage($user->id, 99, now()->subDays(5));                    // before cycle — must not count

        expect($user->messageCredits()->used())->toBe(7);
    });

    it('counts messages in the cycle-start hour even when the cycle began partway through it', function (): void {
        // Regression: the rollup buckets by hour floor (e.g. 22:00) while the cycle
        // can start mid-hour (e.g. registering at 22:16). Messages sent right after
        // registering land in the 22:00 bucket; comparing the floor to the exact
        // start wrongly dropped them, so a fresh account showed "0 this cycle"
        // while the activity chart showed them.
        $user = User::factory()->create();

        $startedAt = now()->startOfHour()->addMinutes(16);
        UserCredit::create([
            'user_id' => $user->id,
            'messages' => 100,
            'started_at' => $startedAt,
            'ends_at' => now()->addDays(30),
        ]);
        $user->refresh();

        // Bucketed at the hour floor (22:00), which is before the 22:16 cycle start.
        insertRollupUsage($user->id, 2, $startedAt->copy()->startOfHour());

        expect($user->messageCredits()->used())->toBe(2);
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
        UserCredit::create([
            'user_id' => $user->id,
            'messages' => 10,
            'started_at' => now()->subDay(),
            'ends_at' => now()->addDays(29),
        ]);
        UserAddon::create(['user_id' => $user->id, 'type' => 'extra_message_credits', 'amount' => 5, 'ends_at' => now()->addMonth()]);
        $user->refresh();

        insertRollupUsage($user->id, 20); // 20 used vs 10 base → base overrun

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
        UserCredit::create([
            'user_id' => $user->id,
            'messages' => 100,
            'started_at' => now()->subDay(),
            'ends_at' => now()->addDays(29),
        ]);
        UserAddon::create(['user_id' => $user->id, 'type' => 'extra_message_credits', 'amount' => 40, 'ends_at' => now()->addMonth()]);
        $user->refresh();

        insertRollupUsage($user->id, 30);

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
