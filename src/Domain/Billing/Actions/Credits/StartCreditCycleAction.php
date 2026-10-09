<?php

namespace Domain\Billing\Actions\Credits;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Models\UserCredit;
use Domain\Billing\Services\UserCreditSystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Opens a new billing cycle in a workspace's message pool: every credit row
 * that is still running is closed, and a fresh row with the plan's message
 * allowance (and an empty usage row) starts now. Cycles never carry unused
 * messages over.
 */
class StartCreditCycleAction
{
    public function __construct(
        private readonly UserCreditSystem $creditSystem,
    ) {}

    public function execute(User $user, int $messages, Carbon $endsAt): UserCredit
    {
        $workspace = $user->workspace();

        $credit = DB::transaction(function () use ($workspace, $messages, $endsAt): UserCredit {
            $workspace->credits()
                ->where('ends_at', '>=', now())
                ->update(['ends_at' => now()->subSecond()]);

            /** @var UserCredit $credit */
            $credit = $workspace->credits()->create([
                'started_at' => $this->nextStartedAt($workspace),
                'messages' => $messages,
                'ends_at' => $endsAt,
            ]);

            $credit->usage()->firstOrCreate();

            return $credit;
        });

        $this->creditSystem->forWorkspace($workspace->id)->startCycle($credit);

        return $credit;
    }

    /**
     * A start time after every existing row: the (user_id, started_at) unique
     * index would otherwise collide when two grants land in the same second
     * (for example plan-upgrade webhooks fired back to back).
     */
    private function nextStartedAt(User $workspace): Carbon
    {
        $lastStartedAt = $workspace->credits()->max('started_at');

        return $lastStartedAt !== null
            ? max(now(), Carbon::parse($lastStartedAt)->addSecond())
            : now();
    }
}
