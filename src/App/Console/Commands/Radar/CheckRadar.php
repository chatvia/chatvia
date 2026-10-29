<?php

declare(strict_types=1);

namespace App\Console\Commands\Radar;

use App\Admin\Issue\Actions\CheckInstallHealthAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Radar's health check every few minutes (see {@see CheckInstallHealthAction}).
 */
#[Description('Check the install for parts that are down, and close Radar issues that are over.')]
#[Signature('chatvia:radar:check')]
class CheckRadar extends Command
{
    public function handle(CheckInstallHealthAction $check): int
    {
        $check->execute();

        $this->info('Radar checked the install.');

        return self::SUCCESS;
    }
}
