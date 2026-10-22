<?php

declare(strict_types=1);

namespace App\Console\Commands\Prune;

use App\Identity\Workspace\Actions\DataExport\PruneExpiredDataExportsAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Scheduled clean-up of GDPR data-export ZIPs.
 */
#[Description('Delete GDPR data exports (the ZIP and the row) whose 24-hour download window has passed, and failed ones older than a week.')]
#[Signature('chatvia:prune:data-exports')]
class PruneExpiredDataExports extends Command
{
    public function handle(PruneExpiredDataExportsAction $prune): int
    {
        $pruned = $prune->execute();

        $this->info("Pruned {$pruned} expired data export(s).");

        return self::SUCCESS;
    }
}
