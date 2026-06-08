<?php

namespace Domain\Billing\Actions\Account;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Events\MessageCreditsLow;
use Domain\Billing\Jobs\UpdateUserCreditsJob;
use Domain\Billing\Services\UserCreditSystem;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Consumes message credits for one assistant reply: increments usage by the
 * model's credit cost under a row lock, refreshes the can-send-messages flag,
 * and fires {@see MessageCreditsLow} when the balance crosses the user's
 * auto-recharge threshold.
 *
 * The distributed lock that serialises concurrent replies stays in
 * {@see UpdateUserCreditsJob} (orchestration); this Action
 * owns the billing logic so it is unit-testable with Event::fake().
 */
class ConsumeMessageCreditsAction
{
    public function __construct(
        private readonly UserCreditSystem $creditSystem,
    ) {}

    public function execute(int $userId, int $modelId, int $additionalMessages = 0): void
    {
        $this->creditSystem->setUserId($userId);

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

        // Refresh the can-send-messages flag after consumption.
        $this->creditSystem->refreshCanSendMessages();

        $this->maybeTriggerAutoRecharge($userId);
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
