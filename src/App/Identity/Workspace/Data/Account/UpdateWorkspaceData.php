<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Data\Account;

use App\Identity\Workspace\Models\Workspace;
use Spatie\LaravelData\Data;

/**
 * Inputs for the admin panel's workspace edit: the workspace's name and its
 * owner's name and email.
 */
class UpdateWorkspaceData extends Data
{
    public function __construct(
        public Workspace $workspace,
        public string $name,
        public string $ownerName,
        public string $ownerEmail,
    ) {}
}
