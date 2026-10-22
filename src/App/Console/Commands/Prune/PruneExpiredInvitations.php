<?php

declare(strict_types=1);

namespace App\Console\Commands\Prune;

use Domain\Membership\Agent\Actions\PruneExpiredAgentChatterInvitationsAction;
use Domain\Membership\Team\Actions\Invitations\PruneExpiredTeamInvitationsAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Scheduled clean-up of invitations that expired a while ago: chatter
 * invitations to an agent ({@see PruneExpiredAgentChatterInvitationsAction})
 * and team invitations ({@see PruneExpiredTeamInvitationsAction}).
 */
#[Description('Delete chatter and team invitations that expired more than 30 days ago.')]
#[Signature('chatvia:prune:invitations')]
class PruneExpiredInvitations extends Command
{
    public function handle(PruneExpiredAgentChatterInvitationsAction $chatterInvitations, PruneExpiredTeamInvitationsAction $teamInvitations): int
    {
        $chatters = $chatterInvitations->execute();
        $teams = $teamInvitations->execute();

        $this->info("Pruned {$chatters} chatter invitation(s) and {$teams} team invitation(s).");

        return self::SUCCESS;
    }
}
