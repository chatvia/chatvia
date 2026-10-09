<?php

namespace App\Console\Commands;

use Domain\Guardrail\Actions\PruneGuardrailEventsAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Scheduled retention for the guardrail trigger log (see
 * {@see PruneGuardrailEventsAction}).
 */
#[Description('Delete guardrail audit rows (guardrail_events) older than the retention window.')]
#[Signature('app:prune-guardrail-events {--days= : Retention in days; defaults to config(chatvia.guardrails.retention_days)}')]
class PruneGuardrailEvents extends Command
{
    public function handle(PruneGuardrailEventsAction $prune): int
    {
        $days = $this->option('days') !== null
            ? max(0, (int) $this->option('days'))
            : (int) config('chatvia.guardrails.retention_days', 90);

        $deleted = $prune->execute($days);

        $this->info("Pruned {$deleted} guardrail event(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
