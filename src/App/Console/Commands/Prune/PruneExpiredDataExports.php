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
#[Description('Delete GDPR data-export ZIPs whose 24-hour download window has passed.')]
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
