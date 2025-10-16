<?php

namespace App\Console\Commands;

use App\Data\Crawler\CrawlerData;
use App\Services\Crawler\Crawler;
use Illuminate\Console\Command;

class TestCommand extends Command
{
    protected $signature = 'app:crawl';

    protected $description = 'Command description';

    public function handle(): void
    {
        $c = app(Crawler::class);

        $result = $c->crawl(
            new CrawlerData(
                url: 'https://chatvia.ai',
            )
        );

        ray($result);
    }
}
