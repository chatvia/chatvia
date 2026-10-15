<?php

namespace Domain\Knowledge\Ingestion\Jobs\Document;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Chunks\IngestSourceChunkAction;
use Domain\Knowledge\Ingestion\Data\Chunking\PageGroupData;
use Domain\Knowledge\Ingestion\Data\Chunking\SourceChunkData;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Ingestion\Enums\ChunkEnhancement;
use Domain\Knowledge\Ingestion\Services\BatchProgress;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Turns a group of pages of a document or text file into chunks (one page
 * when the pages are enhanced). Runs inside the file's ingestion batch; see
 * {@see IngestSourceChunkAction}. After its last failed try, the pages are
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
// An enhanced page waits on model calls for its text, images and tables.
#[Timeout(800)]
// A file deleted before the job runs needs no chunks.
#[DeleteWhenMissingModels]
class DocumentChunkIngestionJob implements ShouldQueue
{
    use Batchable, Queueable;

    public function __construct(
        public Agent $agent,
        public SourceFile $sourceFile,
        public PageGroupData $group,
    ) {}

    /**
     * One job per group of pages, each knowing the pages around it (context
     * for enhancement).
     *
     * @param  Collection<int, PageData>  $pages
     * @return Collection<int, self>
     */
    public static function forPages(Agent $agent, SourceFile $sourceFile, Collection $pages): Collection
    {
        return PageGroupData::split($pages, ChunkEnhancement::for($sourceFile))
            ->map(fn (PageGroupData $group): self => new self($agent, $sourceFile, $group));
    }

    public function handle(IngestSourceChunkAction $action): void
    {
        $batch = $this->batch();

        if ($batch?->cancelled()) {
            return;
        }

        $action->execute(new SourceChunkData(
            agent: $this->agent,
            source: $this->sourceFile,
            group: $this->group,
            progress: BatchProgress::afterCurrentJob($batch),
        ));
    }

    public function failed(Throwable $e): void
    {
        if (ChunkEnhancement::for($this->sourceFile) === ChunkEnhancement::Off) {
            return;
        }

        try {
            app(IngestSourceChunkAction::class)->execute(new SourceChunkData(
                agent: $this->agent,
                source: $this->sourceFile,
                group: $this->group,
                enhancement: ChunkEnhancement::Off,
            ));
        } catch (Throwable $fallback) {
            report($fallback);
        }
    }
}
