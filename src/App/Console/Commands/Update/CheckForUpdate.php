<?php

declare(strict_types=1);

namespace App\Console\Commands\Update;

use App\Admin\Update\Actions\CheckForUpdateAction;
use App\Admin\Update\Services\InstalledVersion;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Runs the daily update check ({@see CheckForUpdateAction}), whose answer
 * the admin panel shows on its dashboard.
 */
#[Description('Look for a newer ChatVia.ai release, for the admin panel\'s update notice.')]
#[Signature('chatvia:update:check')]
class CheckForUpdate extends Command
{
    public function handle(CheckForUpdateAction $checkForUpdate, InstalledVersion $installed): int
    {
        $release = $checkForUpdate->execute();

        if ($release !== null) {
            $this->warn("ChatVia.ai {$release->version} is available (this install runs {$installed->version()}): {$release->url}");
        } else {
            $this->info("No newer release found. This install runs {$installed->version()}.");
        }

        return self::SUCCESS;
    }
}
