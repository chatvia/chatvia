<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Admin\Settings\Actions\TuneWorkersAction;
use App\Admin\Settings\HorizonWorkers;
use App\Admin\Settings\ServerResources;
use App\Admin\Settings\WorkerSizing;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Sizes the queue workers to this server, for installers and scripts: reads
 * its memory and processors ({@see ServerResources}), recommends a worker
 * count per pool ({@see WorkerSizing}) and saves the numbers as the
 * Settings → Platform values. Horizon reads them when it starts, so the
 * command says when a running one needs a restart, and --restart asks it to.
 */
#[Description('Size the queue workers to this server and save them (Settings → Platform). --dry-run only shows them; --restart restarts a running Horizon.')]
#[Signature('app:tune-workers {--dry-run : Show the recommended numbers without saving} {--restart : Restart a running Horizon so the numbers apply}')]
class TuneWorkers extends Command
{
    public function handle(WorkerSizing $sizing, TuneWorkersAction $tune, HorizonWorkers $horizon): int
    {
        $server = ServerResources::detect();
        $workers = $sizing->recommend($server);

        $this->info(sprintf('This server: %s of memory%s, %d processor%s.',
            $this->gigabytes($server->memoryBytes),
            $server->memoryLimitBytes !== null ? sprintf(', %s for this container', $this->gigabytes($server->memoryLimitBytes)) : '',
            $server->cpus,
            $server->cpus === 1 ? '' : 's',
        ));

        $this->table(['Workers', 'Now', 'Recommended'], collect($workers->bySupervisor())
            ->map(fn (int $count, string $name): array => [$name, (int) config("horizon.defaults.{$name}.maxProcesses"), $count])
            ->values()
            ->all());

        if ($this->option('dry-run')) {
            $this->line('Nothing was saved (--dry-run).');

            return self::SUCCESS;
        }

        $tune->execute($workers);
        $this->info('Saved. Change them any time in Settings → Platform → Queue workers.');

        if (! $horizon->isRunning() || ! $horizon->differsFrom($workers->bySupervisor())) {
            return self::SUCCESS;
        }

        if (! $this->option('restart')) {
            $this->warn('Horizon runs other numbers: restart it to apply them (app:tune-workers --restart).');

            return self::SUCCESS;
        }

        $count = $horizon->restart();
        $this->info(sprintf('Asked %d Horizon%s to restart; running jobs finish first.', $count, $count === 1 ? '' : 's'));

        return self::SUCCESS;
    }

    private function gigabytes(int $bytes): string
    {
        return sprintf('%.1f GB', $bytes / 1024 ** 3);
    }
}
