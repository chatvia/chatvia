<?php

namespace App\Identity\Chatter\Actions;

use App\Identity\Chatter\Data\UpdateChatterAccessData;
use Domain\Billing\Services\ChatterTokenAllowance;

/**
 * Applies the Members-sheet access controls to a chatter: sets/clears the
 * monthly token budget (delegated to {@see ChatterTokenAllowance}) and toggles
 * deactivation. Deactivating stamps `deactivated_at`; reactivating clears it.
 */
class UpdateChatterAccessAction
{
    public function __construct(
        private readonly ChatterTokenAllowance $allowance,
    ) {}

    public function execute(UpdateChatterAccessData $data): void
    {
        $chatter = $data->chatter;

        $this->allowance->setLimit($chatter, $data->tokensLimit);

        if ($data->deactivated && ! $chatter->isDeactivated()) {
            $chatter->deactivate($data->deactivationReason);
        } elseif ($data->deactivated) {
            // Already off — keep the reason current without bumping the timestamp.
            $chatter->update(['deactivation_reason' => $data->deactivationReason]);
        } elseif ($chatter->isDeactivated()) {
            $chatter->reactivate();
        }
    }
}
