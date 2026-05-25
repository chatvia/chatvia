<?php

namespace App\Billing\Jobs;

use App\Auth\Models\User;
use App\Billing\Services\UserCreditSystem;
use Domain\Agents\Models\Llm;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateUserCreditsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $userId,
        public int $modelId
    ) {
        $this->onQueue('messages');
    }

    public function handle(UserCreditSystem $creditSystem): void
    {
        $lockKey = "user_credits_lock:{$this->userId}";

        Cache::lock($lockKey, 10)->block(5, function () use ($creditSystem): void {
            $this->updateCredits($creditSystem);
        });
    }

    private function updateCredits(UserCreditSystem $creditSystem): void
    {
        $creditSystem->setUserId($this->userId);

        DB::transaction(function () use ($creditSystem): void {
            /** @var User $user */
            $user = User::query()
                ->lockForUpdate()
                ->find($this->userId);

            if (! $user) {
                Log::warning('UpdateUserCreditsJob: User not found', ['user_id' => $this->userId]);

                return;
            }

            $credit = $user->credit;

            if (! $credit) {
                Log::warning('UpdateUserCreditsJob: No active credit for user', ['user_id' => $this->userId]);

                return;
            }

            // Get credits from model
            $model = Llm::find($this->modelId);
            $credits = $model->credits ?? 1;

            // Update usage in database
            // Messages: increment by LLM cost (e.g., Claude Sonnet 4 with credits=4 uses 4 messages)
            $credit->usage()->increment('messages', $credits);

            // Update cache counters
            $creditSystem->updateAfterCreditConsumption($credits);
        });

        // Refresh the can-send-messages flag after consumption
        $creditSystem->refreshCanSendMessages();
    }
}
