<?php

declare(strict_types=1);

namespace App\Admin\Resources\Workspaces;

use App\Identity\Workspace\Actions\Account\DeleteAccountAction;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;

/**
 * Where a workspace stands, as the admin panel shows it. A workspace is
 * switched off through its owner's account, so the owner decides; one
 * whose deletion has started is switched off with a reason of its own
 * ({@see DeleteAccountAction::DELETION_REASON}) until the queued teardown
 * removes it.
 */
enum WorkspaceStatus: string
{
    case Active = 'active';
    case Deactivated = 'deactivated';
    case BeingDeleted = 'being_deleted';

    public static function for(Workspace $workspace): self
    {
        $owner = $workspace->owner;

        return match (true) {
            ! $owner instanceof User || ! $owner->isDeactivated() => self::Active,
            $owner->deactivation_reason === DeleteAccountAction::DELETION_REASON => self::BeingDeleted,
            default => self::Deactivated,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Deactivated => 'Deactivated',
            self::BeingDeleted => 'Being deleted',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Deactivated => 'danger',
            self::BeingDeleted => 'gray',
        };
    }
}
