<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Enums;

/**
 * Identity discriminator on `users.type`.
 *
 * - `Admin`: internal Chatvia staff with full access.
 * - `Workspace`: an account owner (signs up, manages a team, pays).
 * - `Employee`: a collaborator invited into a workspace's team.
 * - `Chatter`: an end-user that only logs into `/chat` to talk to an agent.
 *   Has no dashboard access and must be granted access to each agent via the
 *   `agent_user` pivot.
 */
enum UserType: string
{
    case Admin = 'admin';
    case Workspace = 'workspace';
    case Employee = 'employee';
    case Chatter = 'chatter';

    /**
     * Account types that own a workspace dashboard. `Admin` is a `Workspace`
     * with additional Filament access, so it must satisfy every workspace check.
     *
     * @return array<int, self>
     */
    public static function workspaceTypes(): array
    {
        return [self::Workspace, self::Admin];
    }
}
