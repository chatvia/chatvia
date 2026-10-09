<?php

declare(strict_types=1);

namespace App\Identity\Chatter\Actions;

use App\Identity\Chatter\Data\UpdateChatterAccessData;
use App\Identity\Workspace\Actions\Account\DeactivateAccountAction;
use App\Identity\Workspace\Actions\Account\ReactivateAccountAction;
use Domain\Billing\Actions\ChatterAllowance\SetChatterMessageLimitAction;
use Illuminate\Support\Facades\DB;

/**
 * Applies the access controls of the Members detail sheet to a chatter in one
 * transaction: sets or clears the monthly message cap and switches the account
 * off or on. Deactivating stamps `deactivated_at`; reactivating clears it.
 */
class UpdateChatterAccessAction
{
    public function __construct(
        private readonly SetChatterMessageLimitAction $setMessageLimit,
        private readonly DeactivateAccountAction $deactivate,
        private readonly ReactivateAccountAction $reactivate,
    ) {}

    public function execute(UpdateChatterAccessData $data): void
    {
        DB::transaction(function () use ($data): void {
            $chatter = $data->chatter;

            $this->setMessageLimit->execute($chatter, $data->messagesLimit);

            if ($data->deactivated && ! $chatter->isDeactivated()) {
                $this->deactivate->execute($chatter, $data->deactivationReason);
            } elseif ($data->deactivated) {
                // Already off — keep the reason current without moving the timestamp.
                $chatter->update(['deactivation_reason' => $data->deactivationReason]);
            } elseif ($chatter->isDeactivated()) {
                $this->reactivate->execute($chatter);
            }
        });
    }
}
