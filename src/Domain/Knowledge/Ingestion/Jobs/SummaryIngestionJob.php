<?php

namespace Domain\Knowledge\Ingestion\Jobs;

use Domain\Knowledge\Ingestion\Actions\Sources\CreateSourceSummaryAction;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
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
use Throwable;

/**
 * Writes the summary chunk of a source on its own queue, next to its chunk
 * batch. See CreateSourceSummaryAction.
 */
#[Queue('ingestion.summary')]
// A model call that fails once often works on the next try.
#[Tries(3)]
// Longer and longer waits, so a busy model service can catch up.
#[Backoff(10, 60, 300)]
// Below the `redis-long` connection's `retry_after`, so no second worker picks it up mid-run.
#[Timeout(1800)]
// Outlasts all three tries and their waits, yet frees a lock a dead worker left behind.
#[UniqueFor(7200)]
// A source deleted before the job runs needs no summary.
#[DeleteWhenMissingModels]
class SummaryIngestionJob implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Queueable;

    public function __construct(
        public readonly SourceFile|SourceText|SourceWebsite $source,
    ) {}

    public function handle(CreateSourceSummaryAction $action): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $action->execute($this->source);
    }

    public function failed(Throwable $e): void
    {
        report($e);
    }

    public function uniqueId(): string
    {
        return 'ingestion:summary:files:'.$this->source::class.":{$this->source->id}";
    }
}
