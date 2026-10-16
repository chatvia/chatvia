<?php

namespace Domain\Knowledge\Ingestion\Jobs\Text;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Chunks\IngestSourceChunkAction;
use Domain\Knowledge\Ingestion\Data\Chunking\PageGroupData;
use Domain\Knowledge\Ingestion\Data\Chunking\SourceChunkData;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Ingestion\Enums\ChunkEnhancement;
use Domain\Knowledge\Ingestion\Services\BatchProgress;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\UniqueFor;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Turns a group of parts of a text source into chunks (one part when the
 * parts are enhanced). Runs inside the text's ingestion batch; see
 * {@see IngestSourceChunkAction}. After its last failed try, the parts are
 * stored without enhancement so they stay searchable.
 */
// A long-running ingestion queue: a page group with enhancement can
// outlast the hand-over on `default`. Inside its batch the batch's
// own queue wins, so DispatchSourceIngestionBatchAction names it too.
#[Queue('ingestion.chunks')]
// A model call that fails once often works on the next try.
#[Tries(3)]
// Longer and longer waits, so a busy model service can catch up.
#[Backoff(10, 60, 300)]
// An enhanced part waits on a model call.
#[Timeout(800)]
// Outlasts all three tries and their waits, yet frees a lock a dead worker left behind.
#[UniqueFor(3600)]
// A text deleted before the job runs needs no chunks.
#[DeleteWhenMissingModels]
class TextChunkIngestionJob implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Queueable;

    public function __construct(
        public readonly Agent $agent,
        public readonly SourceText $sourceText,
        public readonly PageGroupData $group,
    ) {}

    /**
     * @param  Collection<int, PageData>  $pages
     * @return Collection<int, self>
     */
    public static function forPages(Agent $agent, SourceText $sourceText, Collection $pages): Collection
    {
        return PageGroupData::split($pages, ChunkEnhancement::for($sourceText))
            ->map(fn (PageGroupData $group): self => new self($agent, $sourceText, $group));
    }

    public function handle(IngestSourceChunkAction $action): void
    {
        $batch = $this->batch();

        if ($batch?->cancelled()) {
            return;
        }

        $action->execute(new SourceChunkData(
            agent: $this->agent,
            source: $this->sourceText,
            group: $this->group,
            progress: BatchProgress::afterCurrentJob($batch),
        ));
    }

    public function failed(Throwable $e): void
    {
        if (ChunkEnhancement::for($this->sourceText) === ChunkEnhancement::Off) {
            return;
        }

        try {
            app(IngestSourceChunkAction::class)->execute(new SourceChunkData(
                agent: $this->agent,
                source: $this->sourceText,
                group: $this->group,
                enhancement: ChunkEnhancement::Off,
            ));
        } catch (Throwable $fallback) {
            report($fallback);
        }
    }

    public function uniqueId(): string
    {
        return 'agent:'.$this->agent->id.':sources:text:'.$this->sourceText->id.':page:'.($this->group->pages[0]->pageNumber ?? 0);
    }
}
