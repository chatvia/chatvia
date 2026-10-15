<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Actions\Account;

use App\Identity\Workspace\Data\Account\UpdateProfileData;
use App\Identity\Workspace\Data\Account\UpdateWorkspaceData;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;
use Illuminate\Support\Facades\DB;

/**
 * Renames a workspace and saves its owner's name and email, which the admin
 * panel edits together. The owner's part goes through
 * {@see UpdateProfileAction}, so a changed email must be confirmed again,
 * exactly as when the owner changes it. A workspace without an owner only
 * gets its new name.
 */
class UpdateWorkspaceAction
{
    public function __construct(
        private readonly UpdateProfileAction $updateProfile,
    ) {}

    public function execute(UpdateWorkspaceData $data): Workspace
    {
        $workspace = $data->workspace;

        DB::transaction(function () use ($workspace, $data): void {
            $workspace->update(['name' => $data->name]);

            $owner = $workspace->owner;
            if ($owner instanceof User) {
                $this->updateProfile->execute(new UpdateProfileData(
                    user: $owner,
                    name: $data->ownerName,
                    email: $data->ownerEmail,
                ));
            }
        });

        return $workspace;
    }
}
