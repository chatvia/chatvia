<?php

namespace Domain\Billing\Actions\Credits;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Contracts\Entitlements;
use Domain\Billing\Events\MessageCreditsLow;
use Domain\Billing\Jobs\UpdateUserCreditsJob;
use Domain\Billing\Services\AutoRechargePolicy;
use Domain\Billing\Services\UserCreditSystem;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Charges a workspace's message pool for one assistant reply: increments usage
 * by the model's credit cost under a row lock, refreshes the send flag, and
 * fires {@see MessageCreditsLow} when the balance reaches the auto-recharge
 * threshold.
 *
 * Concurrent replies serialise on the `lockForUpdate()` over the users row.
 * {@see UpdateUserCreditsJob} is retried on failure, so the charge is
 * idempotent per `$chargeKey`: the key is claimed before the increment and
 * released only when the increment itself rolls back. A retry after a later
 * step failed (flag refresh, auto-recharge check) skips the increment and only
 * re-runs those steps, which are safe to repeat.
 */
class ConsumeMessageCreditsAction
{
    /** How long a charge key is remembered — far longer than the job's retry window. */
    private const int CHARGE_KEY_TTL_HOURS = 24;

    public function __construct(
        private readonly UserCreditSystem $creditSystem,
        private readonly Entitlements $entitlements,
        private readonly AutoRechargePolicy $autoRecharge,
    ) {}

    public function execute(int $userId, int $modelId, int $additionalMessages = 0, ?string $chargeKey = null): void
    {
        // With billing turned off there is no credit pool to charge. Return
        // quietly so queued charge jobs neither fail nor log a warning for
        // every reply.
        if (! $this->entitlements->enforced()) {
            return;
        }

        $credits = $this->creditSystem->forWorkspace($userId);

        $this->chargeOnce($credits, $userId, $modelId, $additionalMessages, $chargeKey);

        $credits->refreshCanSendMessages();

        $this->raiseLowBalance($userId, $credits->getAvailableMessages());
    }

    /**
     * Cache::add is atomic: false means this charge was already applied.
     */
    private function chargeOnce(UserCreditSystem $credits, int $userId, int $modelId, int $additionalMessages, ?string $chargeKey): void
    {
        $claim = $chargeKey !== null ? "billing:message-charge:{$chargeKey}" : null;

        if ($claim !== null && ! Cache::add($claim, true, now()->addHours(self::CHARGE_KEY_TTL_HOURS))) {
            return;
        }

        try {
            $this->charge($credits, $userId, $modelId, $additionalMessages);
        } catch (Throwable $e) {
            // The increment rolled back, so the retry must be able to charge.
            if ($claim !== null) {
                Cache::forget($claim);
            }

            throw $e;
        }
    }

    private function charge(UserCreditSystem $credits, int $userId, int $modelId, int $additionalMessages): void
    {
        DB::transaction(function () use ($credits, $userId, $modelId, $additionalMessages): void {
            /** @var User|null $user */
            $user = User::query()->lockForUpdate()->find($userId);

            if (! $user) {
                Log::warning('ConsumeMessageCreditsAction: User not found', ['user_id' => $userId]);

                return;
            }

            $credit = $user->credit;

            if (! $credit) {
                Log::warning('ConsumeMessageCreditsAction: No active credit for user', ['user_id' => $userId]);

                return;
            }

            // A model with credits=4 consumes 4 messages, plus any per-turn
            // extras — notably one message per PDF page in a document chat.
            $messages = (Llm::find($modelId)->credits ?? 1) + $additionalMessages;

            $credit->usage()->increment('messages', $messages);

            $credits->updateAfterCreditConsumption($messages);
        });
    }

    /**
     * Fire {@see MessageCreditsLow} when the balance has fallen to the
     * workspace's auto-recharge threshold. The listener decides whether a
     * recharge may actually run (debounce, daily ceiling).
     */
    private function raiseLowBalance(int $userId, int $available): void
    {
        $billing = User::find($userId)?->accountBilling();

        if ($billing !== null && $this->autoRecharge->isBelowThreshold($billing, $available)) {
            MessageCreditsLow::dispatch($userId, $available);
        }
    }
}
