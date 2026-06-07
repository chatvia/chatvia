<?php

namespace Domain\Billing\Jobs;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\Account\AutoRechargeCreditsAction;
use Domain\Billing\Listeners\TriggerAutoRecharge;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessAutoRechargeJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $userId)
    {
        $this->onQueue('messages');
    }

    public function handle(AutoRechargeCreditsAction $action): void
    {
        // Belt-and-braces lock — the listener already debounces by
        // `auto_recharge_last_triggered_at`, but if the queue is backed up
        // and many credits-low events fire before the first job runs, this
        // prevents two concurrent jobs from charging the customer twice.
        $lock = Cache::lock("auto_recharge:{$this->userId}", 60);

        if (! $lock->get()) {
            return;
        }

        try {
            /** @var User|null $user */
            $user = User::find($this->userId);

            if (! $user) {
                return;
            }

            $billing = $user->accountBilling();

            if (! $billing || ! $billing->auto_recharge_enabled) {
                return;
            }

            // Re-check the debounce window now that we hold the lock — a
            // concurrent listener may have just queued another job that
            // arrived here first.
            if ($billing->auto_recharge_last_triggered_at
                && $billing->auto_recharge_last_triggered_at->gt(now()->subHour())) {
                return;
            }

            // Re-check the rolling-24h spend ceiling under the lock (mirrors the
            // listener guard) so a burst of queued jobs can't blow past it.
            if (TriggerAutoRecharge::dailyRechargeCount($user)
                >= (int) config('billing.addons.auto_recharge_credits.max_per_day', 3)) {
                return;
            }

            $unitMessages = (int) config('billing.addons.auto_recharge_credits.unit_messages', 1000);
            $amount = (int) $billing->auto_recharge_amount;
            $units = max(1, (int) ceil($amount / $unitMessages));

            $action->execute($user, $units);
        } catch (Throwable $e) {
            Log::error('Auto-recharge failed', [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            $lock->release();
        }
    }
}
