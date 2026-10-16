<?php

namespace Domain\Membership\Team\Exceptions;

use RuntimeException;

/**
 * Someone tried to remove the team's owner from their own team or change
 * their role. The owner holds the team rather than a membership, so neither
 * is possible. The HTTP layer turns it into a 403 (see bootstrap/app.php).
 */
final class TeamOwnerImmutableException extends RuntimeException
{
    public static function forRemoval(): self
    {
        return new self(__('Cannot remove the team owner.'));
    }

    public static function forLeaving(): self
    {
        return new self(__('Team owners cannot leave their own team. Transfer ownership first or delete the team.'));
    }

    public static function forRoleChange(): self
    {
        return new self(__("Cannot change the owner's role."));
    }
}
