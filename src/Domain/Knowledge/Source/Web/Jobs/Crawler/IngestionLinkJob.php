<?php

namespace Domain\Knowledge\Source\Web\Jobs\Crawler;

use Domain\Knowledge\Source\Web\Data\Crawler\LinkData;
use Illuminate\Contracts\Queue\ShouldQueue;

class IngestionLinkJob implements ShouldQueue
{
    use \Illuminate\Foundation\Queue\Queueable;

    public function __construct(public LinkData $link) {}

    public function handle(): void {}
}
