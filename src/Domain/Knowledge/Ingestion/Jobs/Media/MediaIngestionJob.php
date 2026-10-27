<?php

namespace Domain\Knowledge\Ingestion\Jobs\Media;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Pipeline\MarkSourceFailedAction;
use Domain\Knowledge\Ingestion\Actions\Sources\IngestMediaAction;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\UniqueFor;
use Throwable;

/**
 * Transcribes an audio or video file and starts its chunk batch. See
 * {@see IngestMediaAction}. Long recordings take hours to transcribe, hence the
 * long timeout and the dedicated audio and video queues.
 */
// One retry, as a transcription that runs for hours can hit a passing service error.
#[Tries(2)]
// Below the `redis-media` connection's `retry_after`, so no second worker picks it up mid-run.
#[Timeout(86000)]
// Two days: outlasts both tries, yet frees a lock a dead worker left behind.
#[UniqueFor(172800)]
// A file deleted before the job runs needs no transcript.
#[DeleteWhenMissingModels]
class MediaIngestionJob implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Queueable;

    public function __construct(public readonly SourceFile $sourceFile, public readonly Agent $agent)
    {
        // Chosen per file (audio or video), so an attribute cannot name it.
        if ($sourceFile->isVideoFile()) {
            $this->onQueue('ingestion.video');
        } elseif ($sourceFile->isAudioFile()) {
            $this->onQueue('ingestion.audio');
        }
    }

    public function handle(IngestMediaAction $action): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $action->execute($this->sourceFile, $this->agent, $this->job->getJobId());
    }

    public function uniqueId(): string
    {
        return "ingestion:media:{$this->sourceFile->id}";
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return [
            "source_file:{$this->sourceFile->id}",
            'ingestion_job',
            'media',
        ];
    }

    public function failed(Throwable $exception): void
    {
        app(MarkSourceFailedAction::class)->execute($this->sourceFile, $this->agent, $exception);
    }
}
