<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Enums;

/**
 * A staff account's role in its workspace (`workspace_user.role`). The owner
 * holds every permission and can delete the workspace; a member's reach comes
 * from their team roles. Exactly one owner per workspace.
 */
enum WorkspaceRole: string
{
    case Owner = 'owner';
    case Member = 'member';
}
