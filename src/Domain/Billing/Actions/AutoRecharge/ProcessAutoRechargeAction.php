<?php

namespace Domain\Billing\Actions\AutoRecharge;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Jobs\ProcessAutoRechargeJob;
use Domain\Billing\Models\UserAddon;
use Domain\Billing\Services\AutoRechargePolicy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs one queued auto-recharge ({@see ProcessAutoRechargeJob}) for a
 * workspace: takes a per-workspace lock, re-checks the
 * {@see AutoRechargePolicy} under it, and charges the configured amount.
 *
 * The listener that queued the job already checked the policy, but when the
 * queue is backed up many low-balance events can queue jobs before the first
 * one runs. The lock and the re-check make sure only one of them charges the
 * card. Returns the granted add-on, or null when nothing was charged.
 */
class ProcessAutoRechargeAction
{
    private const int LOCK_SECONDS = 60;

    public function __construct(
        private readonly AutoRechargePolicy $policy,
        private readonly AutoRechargeCreditsAction $recharge,
    ) {}

    public function execute(int $workspaceId): ?UserAddon
    {
        $lock = Cache::lock("auto_recharge:{$workspaceId}", self::LOCK_SECONDS);

        if (! $lock->get()) {
            return null;
        }

        try {
            $workspace = User::find($workspaceId);

            if ($workspace === null || ! $this->policy->mayRechargeNow($workspace)) {
                return null;
            }

            return $this->recharge->execute($workspace, $this->policy->unitsFor($workspace->accountBillingOrFail()));
        } catch (Throwable $e) {
            Log::error('Auto-recharge failed', [
                'user_id' => $workspaceId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            $lock->release();
        }
    }
}
