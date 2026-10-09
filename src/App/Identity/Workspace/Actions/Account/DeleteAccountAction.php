<?php

namespace App\Identity\Workspace\Actions\Account;

use App\Identity\Workspace\Jobs\DeleteWorkspaceJob;
use App\Identity\Workspace\Models\User;

/**
 * Starts the deletion of a workspace account the owner has confirmed by email.
 * The account (and every member, through the deactivation cascade) is switched
 * off at once so nobody can sign back in, and the teardown of its data runs on
 * the queue ({@see DeleteWorkspaceJob}) because a large workspace takes far
 * longer than a request may.
 */
class DeleteAccountAction
{
    public function __construct(
        private readonly DeactivateAccountAction $deactivate,
    ) {}

    public function execute(User $workspace): void
    {
        $this->deactivate->execute($workspace, 'Account deletion in progress');

        DeleteWorkspaceJob::dispatch($workspace->id);
    }
}
