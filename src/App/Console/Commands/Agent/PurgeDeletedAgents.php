<?php

declare(strict_types=1);

namespace App\Console\Commands\Agent;

use Domain\Agent\Actions\Account\QueueStaleAgentPurgesAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Daily backstop for agent deletes whose purge failed every try (see
 * {@see QueueStaleAgentPurgesAction}).
 */
#[Description('Queue the purge again for agents deleted more than a day ago whose data is still there.')]
#[Signature('chatvia:agent:purge-deleted')]
class PurgeDeletedAgents extends Command
{
    public function handle(QueueStaleAgentPurgesAction $queuePurges): int
    {
        $queued = $queuePurges->execute();

        $this->info("Queued the purge of {$queued} deleted agent(s).");

        return self::SUCCESS;
    }
}
