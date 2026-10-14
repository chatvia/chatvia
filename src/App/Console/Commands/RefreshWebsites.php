<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Domain\Knowledge\Source\Web\Actions\Refresh\RefreshDueWebsitesAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Hourly website refresh: crawls and trains again the websites whose refresh
 * interval has passed ({@see RefreshDueWebsitesAction}).
 */
#[Description('Crawl and train again the websites whose refresh interval (daily, weekly, monthly) has passed. Only changed pages are embedded again.')]
#[Signature('app:refresh-websites')]
class RefreshWebsites extends Command
{
    public function handle(RefreshDueWebsitesAction $refresh): int
    {
        $started = $refresh->execute();

        $this->info("Started the refresh of {$started} website(s).");

        return self::SUCCESS;
    }
}
