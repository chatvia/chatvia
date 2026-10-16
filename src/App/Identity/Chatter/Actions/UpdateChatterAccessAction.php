<?php

declare(strict_types=1);

namespace App\Identity\Chatter\Actions;

use App\Identity\Chatter\Data\UpdateChatterAccessData;
use App\Identity\Workspace\Actions\Account\DeactivateAccountAction;
use App\Identity\Workspace\Actions\Account\ReactivateAccountAction;
use App\Identity\Workspace\Actions\MessageLimits\SetMessageLimitAction;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelData\Optional;

/**
 * Applies the access controls of the Members detail sheet to a chatter in one
 * transaction: sets or clears the monthly message cap and switches the account
 * off or on. Deactivating stamps `deactivated_at`; reactivating clears it.
 */
class UpdateChatterAccessAction
{
    public function __construct(
        private readonly SetMessageLimitAction $setMessageLimit,
        private readonly DeactivateAccountAction $deactivate,
        private readonly ReactivateAccountAction $reactivate,
    ) {}

    public function execute(UpdateChatterAccessData $data): void
    {
        DB::transaction(function () use ($data): void {
            $chatter = $data->chatter;

            if (! $data->messagesLimit instanceof Optional) {
                $this->setMessageLimit->execute($chatter, $data->messagesLimit);
            }

            $reason = $data->deactivationReason instanceof Optional ? $chatter->deactivation_reason : $data->deactivationReason;

            if ($data->deactivated instanceof Optional) {
                // Reason-only update: keep it current without moving the timestamp.
                if (! $data->deactivationReason instanceof Optional && $chatter->isDeactivated()) {
                    $chatter->update(['deactivation_reason' => $reason]);
                }

                return;
            }

            if ($data->deactivated && ! $chatter->isDeactivated()) {
                $this->deactivate->execute($chatter, $reason);
            } elseif ($data->deactivated) {
                // Already off — keep the reason current without moving the timestamp.
                $chatter->update(['deactivation_reason' => $reason]);
            } elseif ($chatter->isDeactivated()) {
                $this->reactivate->execute($chatter);
            }
        });
    }
}
