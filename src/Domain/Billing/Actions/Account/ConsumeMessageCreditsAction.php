<?php

namespace Domain\Billing\Actions\Account;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Events\MessageCreditsLow;
use Domain\Billing\Jobs\UpdateUserCreditsJob;
use Domain\Billing\Services\UserCreditSystem;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Consumes message credits for one assistant reply: increments usage by the
 * model's credit cost under a row lock, refreshes the can-send-messages flag,
 * and fires {@see MessageCreditsLow} when the balance crosses the user's
 * auto-recharge threshold.
 *
 * Concurrent replies serialise on the `lockForUpdate()` over the users row
 * inside the transaction below. {@see UpdateUserCreditsJob} is retried on
 * failure, so the charge is idempotent per `$chargeKey`: the key is claimed
 * before the increment and released only when the increment itself rolls
 * back. A retry after a later step failed (flag refresh, auto-recharge check)
 * skips the increment and only re-runs those steps, which are safe to repeat.
 */
class ConsumeMessageCreditsAction
{
    /** How long a charge key is remembered — far longer than the job's retry window. */
    private const int CHARGE_KEY_TTL_HOURS = 24;

    public function __construct(
        private readonly UserCreditSystem $creditSystem,
    ) {}

    public function execute(int $userId, int $modelId, int $additionalMessages = 0, ?string $chargeKey = null): void
    {
        $this->creditSystem->forWorkspace($userId);

        $claim = $chargeKey !== null ? "billing:message-charge:{$chargeKey}" : null;

        // Cache::add is atomic: false means this charge was already applied.
        if ($claim === null || Cache::add($claim, true, now()->addHours(self::CHARGE_KEY_TTL_HOURS))) {
            try {
                $this->charge($userId, $modelId, $additionalMessages);
            } catch (Throwable $e) {
                // The increment rolled back, so the retry must be able to charge.
                if ($claim !== null) {
                    Cache::forget($claim);
                }

                throw $e;
            }
        }

        // Refresh the can-send-messages flag after consumption.
        $this->creditSystem->refreshCanSendMessages();

        $this->maybeTriggerAutoRecharge($userId);
    }

    private function charge(int $userId, int $modelId, int $additionalMessages): void
    {
        DB::transaction(function () use ($userId, $modelId, $additionalMessages): void {
            /** @var User|null $user */
            $user = User::query()
                ->lockForUpdate()
                ->find($userId);

            if (! $user) {
                Log::warning('ConsumeMessageCreditsAction: User not found', ['user_id' => $userId]);

                return;
            }

            $credit = $user->credit;

            if (! $credit) {
                Log::warning('ConsumeMessageCreditsAction: No active credit for user', ['user_id' => $userId]);

                return;
            }

            // Messages: increment by the model's credit cost (e.g. a model with
            // credits=4 consumes 4 messages) plus any per-turn extras — notably
            // one message per PDF page in a document-chat turn.
            $model = Llm::find($modelId);
            $credits = ($model->credits ?? 1) + $additionalMessages;

            $credit->usage()->increment('messages', $credits);

            $this->creditSystem->updateAfterCreditConsumption($credits);
        });
    }

    /**
     * After consumption, dispatch {@see MessageCreditsLow} if the user's
     * available balance has crossed their configured auto-recharge threshold.
     * The TriggerAutoRecharge listener guards against back-to-back fires; this
     * only needs the if-eligible check.
     */
    private function maybeTriggerAutoRecharge(int $userId): void
    {
        /** @var User|null $user */
        $user = User::find($userId);

        if (! $user) {
            return;
        }

        $billing = $user->accountBilling();

        if (! $billing || ! $billing->auto_recharge_enabled) {
            return;
        }

        $available = $this->creditSystem->getAvailableMessages();

        if ($available > $billing->auto_recharge_threshold) {
            return;
        }

        MessageCreditsLow::dispatch($userId, $available);
    }
}
