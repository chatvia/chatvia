<?php

namespace App\Models;

use App\Contracts\ChunkableContract;
use App\Data\Crawler\CrawlerData;
use App\Events\WebsiteCrawlerEvent;
use App\Jobs\Crawler\WebsiteCrawlerJob;
use App\Jobs\Crawler\WebsiteIngestionJob;
use App\Traits\Chunkable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentWebsite extends Model implements ChunkableContract
{
    use Chunkable;

    protected $guarded = ['id'];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    protected function casts(): array
    {
        return [
            'options' => 'json',
        ];
    }

    public function startTraining(): void
    {
        WebsiteIngestionJob::dispatch($this);
    }

    public function startCrawling(): void
    {
        // Update status to crawling
        $this->update(['status' => 'crawling']);

        // Prepare crawler data from the website's URL and options
        $crawlerData = CrawlerData::from([
            'url' => $this->url,
            'slow_crawling' => $this->options['slow_crawling'] ?? false,
            'include_paths' => $this->options['include_paths'] ?? null,
            'exclude_paths' => $this->options['exclude_paths'] ?? null,
        ]);

        // Dispatch crawler event
        WebsiteCrawlerEvent::dispatch($this);

        // Dispatch crawler job
        WebsiteCrawlerJob::dispatch($this, $crawlerData);
    }

    public function urls(): HasMany
    {
        return $this->hasMany(AgentWebsiteUrl::class);
    }

    public function untrainedUrls(): HasMany
    {
        return $this->hasMany(AgentWebsiteUrl::class)->where('status', 'pending');
    }
}
