<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Jobs\Text;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Pipeline\MarkSourceFailedAction;
use Domain\Knowledge\Ingestion\Actions\Sources\IngestTextAction;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Queue;
use Throwable;

/**
 * Splits a text source and starts its chunk batch. See {@see IngestTextAction}.
 */
#[Queue('ingestion.text')]
// A text deleted before the job runs needs no ingestion.
#[DeleteWhenMissingModels]
class TextIngestionJob implements ShouldQueue
{
    use Batchable, Queueable;

    public function __construct(public SourceText $sourceText, public Agent $agent) {}

    public function handle(IngestTextAction $action): void
    {
        $action->execute($this->sourceText, $this->agent);
    }

    public function failed(Throwable $exception): void
    {
        app(MarkSourceFailedAction::class)->execute($this->sourceText, $this->agent, $exception);
    }
}
