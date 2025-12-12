<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class Firecrawl
{
    private function client(): PendingRequest
    {
        $baseUrl = rtrim(config('services.firecrawl.endpoint'), '/').'/';

        return Http::withToken(config('services.firecrawl.key'))
            ->baseUrl($baseUrl)
            ->throw()
            ->acceptJson();

    }

    public function crawl(string $url, int $limit)
    {
        return $this->client()->post('crawl', [
            'url' => $url,
            'limit' => $limit
        ])->json();
    }

    public function crawlStatus(string $jobId)
    {
        return $this->client()->get('crawl/'.$jobId)->json();
    }

    public function map(string $url, int $limit)
    {
        return $this->client()->post('map', [
            'url' => $url,
            'limit' => $limit
        ])->json();
    }
}
