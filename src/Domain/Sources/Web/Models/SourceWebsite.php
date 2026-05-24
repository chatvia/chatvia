<?php

namespace Domain\Sources\Web\Models;

use Domain\Ingestion\Contracts\ChunkableContract;
use Domain\Sources\Web\Data\Crawler\CrawlerData;
use Domain\Sources\Web\Events\WebsiteCrawlerEvent;
use Domain\Sources\Web\Jobs\Crawler\WebsiteCrawlerJob;
use Domain\Ingestion\Jobs\Website\WebsiteIngestionJob;
use Support\Traits\Chunkable;
use Support\Traits\HasStorageFile;
use Support\Traits\SupportsExternalId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentSource;

class SourceWebsite extends Model implements ChunkableContract
{
    use Chunkable, HasStorageFile, SupportsExternalId;

    protected $guarded = ['id'];

    public function agents(): MorphToMany
    {
        return $this->morphToMany(Agent::class, 'sourceable', 'agent_sources')
            ->using(AgentSource::class)
            ->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'options' => 'json',
        ];
    }

    public function startTraining(?int $agentId = null): void
    {
        // If agentId is provided, train for that specific agent
        // Otherwise, train for all agents this website is attached to
        if ($agentId) {
            $agent = Agent::find($agentId);
            WebsiteIngestionJob::dispatch($this, $agent);
        } else {
            $this->agents->each(function ($agent) {
                WebsiteIngestionJob::dispatch($this, $agent);
            });
        }
    }

    public function startCrawling(?int $agentId = null): void
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

        // Dispatch crawler job and event for each agent
        if ($agentId) {
            $agent = Agent::find($agentId);
            WebsiteCrawlerEvent::dispatch($this, $agent);
            WebsiteCrawlerJob::dispatch($this, $crawlerData, $agent);
        } else {
            $this->agents->each(function ($agent) use ($crawlerData) {
                WebsiteCrawlerEvent::dispatch($this, $agent);
                WebsiteCrawlerJob::dispatch($this, $crawlerData, $agent);
            });
        }
    }

    public function urls(): HasMany
    {
        return $this->hasMany(AgentWebsiteUrl::class, 'source_website_id');
    }

    public function untrainedUrls(): HasMany
    {
        return $this->hasMany(AgentWebsiteUrl::class, 'source_website_id')
            ->where('status', 'pending');
    }
}
