<?php

namespace Domain\Knowledge\Ingestion\Jobs;

use Domain\Knowledge\Ingestion\Actions\Sources\CreateSourceSummaryAction;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Llm\Usage\AttributesTokenUsage;
use Domain\Llm\Usage\TracksTokenUsage;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SummaryIngestionJob implements AttributesTokenUsage, ShouldBeUnique, ShouldQueue
{
    use Batchable, Queueable, SerializesModels, TracksTokenUsage;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public bool $deleteWhenMissingModels = true;

    public int $timeout = 1800;

    public function __construct(
        public SourceFile|SourceText|SourceWebsite $source,
    ) {
        $this->onQueue('ingestion.summary');
    }

    public function tokenWorkspaceId(): ?int
    {
        return $this->source->user_id;
    }

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
        return 'ingestion:summary:files:'.$this->source::class.':'.$this->source->id;
    }
}
