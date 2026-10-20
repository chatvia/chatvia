<?php

declare(strict_types=1);

namespace App\Console\Commands\Prune;

use Domain\Form\Actions\PruneWorkspaceLeadsAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Daily lead retention: deletes the leads of each workspace that set a lead
 * retention window ({@see PruneWorkspaceLeadsAction}).
 */
#[Description('Delete captured leads (submitted form_submissions) older than the lead retention each workspace set in its security policy. Workspaces without one keep their leads.')]
#[Signature('chatvia:prune:leads {--batch=5000 : How many rows to delete per batch}')]
class PruneLeads extends Command
{
    public function handle(PruneWorkspaceLeadsAction $prune): int
    {
        $deleted = $prune->execute(max(1, (int) $this->option('batch')));

        $this->info("Pruned {$deleted} lead(s) past their workspace's retention.");

        return self::SUCCESS;
    }
}
