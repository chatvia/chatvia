<?php

namespace App\Identity\Chatter\Actions;

use App\Identity\Chatter\Data\UpdateChatterAccessData;
use App\Identity\Workspace\Actions\Account\DeactivateAccountAction;
use App\Identity\Workspace\Actions\Account\ReactivateAccountAction;
use Domain\Billing\Services\ChatterMessageAllowance;

/**
 * Applies the Members-sheet access controls to a chatter: sets/clears the
 * monthly message cap (delegated to {@see ChatterMessageAllowance}) and toggles
 * deactivation. Deactivating stamps `deactivated_at`; reactivating clears it.
 */
class UpdateChatterAccessAction
{
    public function __construct(
        private readonly ChatterMessageAllowance $allowance,
        private readonly DeactivateAccountAction $deactivate,
        private readonly ReactivateAccountAction $reactivate,
    ) {}

    public function execute(UpdateChatterAccessData $data): void
    {
        $chatter = $data->chatter;

        $this->allowance->setLimit($chatter, $data->messagesLimit);

        if ($data->deactivated && ! $chatter->isDeactivated()) {
            $this->deactivate->execute($chatter, $data->deactivationReason);
        } elseif ($data->deactivated) {
            // Already off — keep the reason current without bumping the timestamp.
            $chatter->update(['deactivation_reason' => $data->deactivationReason]);
        } elseif ($chatter->isDeactivated()) {
            $this->reactivate->execute($chatter);
        }
    }
}
