<?php

namespace Domain\Knowledge\Ingestion\Jobs\TextFile;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Documents\IngestTextFileAction;
use Domain\Knowledge\Ingestion\Actions\Pipeline\MarkSourceFailedAction;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\UniqueFor;
use Throwable;

/**
 * Splits a text file (.txt, .md, or an ebook read as Markdown) and starts its
 * chunk batch, without the document parser. See {@see IngestTextFileAction}.
 */
#[Queue('ingestion.text')]
// One try: a failed read goes straight to failed(), which marks the file failed.
#[Tries(1)]
// A large ebook takes a while to read and split.
#[Timeout(600)]
// Longer than a run, so a worker that dies mid-run cannot hold the lock for good.
#[UniqueFor(3600)]
// A file deleted before the job runs needs no ingestion.
#[DeleteWhenMissingModels]
class TextFileIngestionJob implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Queueable;

    public function __construct(public readonly SourceFile $sourceFile, public readonly Agent $agent) {}

    public function handle(IngestTextFileAction $action): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $action->execute($this->sourceFile, $this->agent, $this->job->getJobId());
    }

    public function uniqueId(): string
    {
        return "ingestion:files:{$this->sourceFile->id}";
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return [
            "source_file:{$this->sourceFile->id}",
            'ingestion_job',
        ];
    }

    public function failed(Throwable $exception): void
    {
        app(MarkSourceFailedAction::class)->execute($this->sourceFile, $this->agent, $exception);
    }
}
