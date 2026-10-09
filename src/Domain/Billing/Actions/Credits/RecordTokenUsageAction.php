<?php

namespace Domain\Billing\Actions\Credits;

use Domain\Billing\Services\UserCreditSystem;

/**
 * Adds LLM tokens to the `tokens` counter of a workspace's current billing
 * cycle. Analytics only: tokens never gate or bill (messages do); this is the
 * "tokens used this cycle" figure on the usage page. Does nothing when the
 * workspace has no running cycle.
 */
class RecordTokenUsageAction
{
    public function __construct(
        private readonly UserCreditSystem $creditSystem,
    ) {}

    public function execute(int $workspaceId, int $tokens): void
    {
        if ($tokens <= 0) {
            return;
        }

        $this->creditSystem->forWorkspace($workspaceId)
            ->activeCredit()
            ?->usage()
            ->increment('tokens', $tokens);
    }
}
