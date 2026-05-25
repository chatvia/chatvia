<?php

namespace Domain\Sources\Web\Models;

use Domain\Agents\Collections\AgentCollection;
use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentSource;
use Domain\Ingestion\Contracts\ChunkableContract;
use Domain\Ingestion\Jobs\Website\WebsiteIngestionJob;
use Domain\Ingestion\Models\Chunk;
use Domain\Sources\Web\Data\Crawler\CrawlerData;
use Domain\Sources\Web\Events\WebsiteCrawlerEvent;
use Domain\Sources\Web\Jobs\Crawler\WebsiteCrawlerJob;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Carbon;
use Support\Traits\Chunkable;
use Support\Traits\HasStorageFile;
use Support\Traits\SupportsExternalId;

/**
 * @property int $id
 * @property string|null $external_id
 * @property int $user_id
 * @property string|null $language
 * @property string|null $document_classification
 * @property string $url
 * @property string|null $filepath
 * @property string|null $checksum
 * @property array<array-key, mixed>|null $options
 * @property int|null $expected_chunks
 * @property string|null $internal_ingestion_job_id
 * @property string|null $internal_chunking_batch_id
 * @property string $status
 * @property int $took_in_seconds
 * @property string|null $failure_reason
 * @property int $retrieval_count
 * @property string|null $last_retrieved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AgentSource|null $pivot
 * @property-read AgentCollection<int, Agent> $agents
 * @property-read int|null $agents_count
 * @property-read Collection<int, Chunk> $chunks
 * @property-read int|null $chunks_count
 * @property-read Chunk|null $summary
 * @property-read Collection<int, AgentWebsiteUrl> $untrainedUrls
 * @property-read int|null $untrained_urls_count
 * @property-read Collection<int, AgentWebsiteUrl> $urls
 * @property-read int|null $urls_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceWebsite newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceWebsite newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceWebsite query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceWebsite whereChecksum($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceWebsite whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceWebsite whereDocumentClassification($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceWebsite whereExpectedChunks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceWebsite whereExternalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceWebsite whereFailureReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceWebsite whereFilepath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceWebsite whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceWebsite whereInternalChunkingBatchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceWebsite whereInternalIngestionJobId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceWebsite whereLanguage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceWebsite whereLastRetrievedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceWebsite whereOptions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceWebsite whereRetrievalCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceWebsite whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceWebsite whereTookInSeconds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceWebsite whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceWebsite whereUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceWebsite whereUserId($value)
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
class SourceWebsite extends Model implements ChunkableContract
{
    use Chunkable, HasStorageFile, SupportsExternalId;

    /**
     * @return MorphToMany<Agent, $this, AgentSource>
     */
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
            $agent = Agent::findOrFail($agentId);
            WebsiteIngestionJob::dispatch($this, $agent);
        } else {
            $this->agents->each(function (Agent $agent): void {
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
            $agent = Agent::findOrFail($agentId);
            WebsiteCrawlerEvent::dispatch($this, $agent);
            WebsiteCrawlerJob::dispatch($this, $crawlerData, $agent);
        } else {
            $this->agents->each(function (Agent $agent) use ($crawlerData): void {
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
