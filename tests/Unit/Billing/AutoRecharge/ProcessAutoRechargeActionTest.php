<?php

use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\AutoRecharge\AutoRechargeCreditsAction;
use Domain\Billing\Actions\AutoRecharge\ProcessAutoRechargeAction;
use Domain\Billing\Events\MessageCreditsLow;
use Domain\Billing\Jobs\ProcessAutoRechargeJob;
use Domain\Billing\Listeners\TriggerAutoRecharge;
use Domain\Billing\Models\UserAddon;
use Domain\Billing\Services\AutoRechargePolicy;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Mockery as m;

/**
 * The auto-recharge rules live in one place (AutoRechargePolicy) and are asked
 * by the listener that queues the recharge and again, under a lock, by the
 * Action the queued job runs.
 */
afterEach(function (): void {
    m::close();
});

function parWorkspace(array $billing = []): User
{
    $owner = User::factory()->workspace()->create();
    $owner->billing()->updateOrCreate([], [
        'auto_recharge_enabled' => true,
        'auto_recharge_threshold' => 100,
        'auto_recharge_amount' => 2500,
        ...$billing,
    ]);

    return $owner->fresh();
}

function parExpectRecharge(int $times, int $units = 3): void
{
    $recharge = m::mock(AutoRechargeCreditsAction::class);
    $recharge->shouldReceive('execute')->times($times)->with(m::type(User::class), $units)->andReturnNull();
    app()->instance(AutoRechargeCreditsAction::class, $recharge);
}

it('charges the configured amount rounded up to whole units', function (): void {
    config(['billing.addons.auto_recharge_credits.unit_messages' => 1000]);
    parExpectRecharge(times: 1, units: 3);

    app(ProcessAutoRechargeAction::class)->execute(parWorkspace()->id);
});

it('does not charge when auto-recharge is off', function (): void {
    parExpectRecharge(times: 0);

    app(ProcessAutoRechargeAction::class)->execute(parWorkspace(['auto_recharge_enabled' => false])->id);
});

it('does not charge again within an hour of the last recharge', function (): void {
    parExpectRecharge(times: 0);

    app(ProcessAutoRechargeAction::class)->execute(
        parWorkspace(['auto_recharge_last_triggered_at' => now()->subMinutes(30)])->id,
    );
});

it('stops at the daily ceiling', function (): void {
    config(['billing.addons.auto_recharge_credits.max_per_day' => 2]);
    parExpectRecharge(times: 0);
    $owner = parWorkspace();

    UserAddon::factory()->count(2)->create([
        'user_id' => $owner->id,
        'type' => 'auto_recharge_credits',
        'started_at' => now()->subHours(3),
    ]);

    app(ProcessAutoRechargeAction::class)->execute($owner->id);
});

it('skips while another recharge of the same workspace holds the lock', function (): void {
    parExpectRecharge(times: 0);
    $owner = parWorkspace();

    $lock = Cache::lock("auto_recharge:{$owner->id}", 60);
    $lock->get();

    try {
        expect(app(ProcessAutoRechargeAction::class)->execute($owner->id))->toBeNull();
    } finally {
        $lock->release();
    }
});

it('queues a recharge from the credits-low event only when the policy allows it', function (): void {
    Bus::fake([ProcessAutoRechargeJob::class]);
    $allowed = parWorkspace();
    $debounced = parWorkspace(['auto_recharge_last_triggered_at' => now()->subMinutes(5)]);

    app(TriggerAutoRecharge::class)->handle(new MessageCreditsLow($allowed->id, 50));
    app(TriggerAutoRecharge::class)->handle(new MessageCreditsLow($debounced->id, 50));

    Bus::assertDispatched(ProcessAutoRechargeJob::class, fn (ProcessAutoRechargeJob $job): bool => $job->userId === $allowed->id);
    Bus::assertDispatchedTimes(ProcessAutoRechargeJob::class, 1);
});

it('treats a balance at or under the threshold as low', function (): void {
    $billing = parWorkspace()->accountBillingOrFail();
    $policy = app(AutoRechargePolicy::class);

    expect($policy->isBelowThreshold($billing, 100))->toBeTrue()
        ->and($policy->isBelowThreshold($billing, 101))->toBeFalse()
        ->and($policy->isEnabled(null))->toBeFalse();
});
