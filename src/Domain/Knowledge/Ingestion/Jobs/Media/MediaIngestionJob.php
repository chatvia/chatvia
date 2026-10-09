<?php

namespace Domain\Knowledge\Ingestion\Jobs\Media;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Pipeline\MarkSourceFailedAction;
use Domain\Knowledge\Ingestion\Actions\Sources\IngestMediaAction;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Llm\Usage\AttributesTokenUsage;
use Domain\Llm\Usage\TracksTokenUsage;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Transcribes an audio or video file and starts its chunk batch. See
 * {@see IngestMediaAction}. Long recordings take hours to transcribe, hence the
 * long timeout and the dedicated audio and video queues.
 */
class MediaIngestionJob implements AttributesTokenUsage, ShouldBeUnique, ShouldQueue
{
    use Batchable, InteractsWithQueue, Queueable, SerializesModels, TracksTokenUsage;

    public bool $deleteWhenMissingModels = true;

    public int $tries = 2;

    public int $timeout = 86000;

    public function __construct(public SourceFile $sourceFile, public Agent $agent)
    {
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

    public function tokenWorkspaceId(): ?int
    {
        return $this->sourceFile->user_id;
    }

    public function uniqueId(): string
    {
        return 'ingestion:media:'.$this->sourceFile->id;
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return [
            'source_file:'.$this->sourceFile->id,
            'ingestion_job',
            'media',
        ];
    }

    public function failed(Throwable $exception): void
    {
        app(MarkSourceFailedAction::class)->execute($this->sourceFile, $this->agent, $exception);
    }
}
