<?php

namespace Domain\Source\Web\Jobs\Crawler;

use Domain\Source\Web\Data\Crawler\LinkData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class IngestionLinkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public LinkData $link) {}

    public function handle(): void {}
}
