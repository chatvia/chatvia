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
use Throwable;

/**
 * Splits a text source and starts its chunk batch. See {@see IngestTextAction}.
 */
class TextIngestionJob implements ShouldQueue
{
    use Batchable, Queueable;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public SourceText $sourceText, public Agent $agent)
    {
        $this->onQueue('ingestion.text');
    }

    public function handle(IngestTextAction $action): void
    {
        $action->execute($this->sourceText, $this->agent);
    }

    public function failed(Throwable $exception): void
    {
        app(MarkSourceFailedAction::class)->execute($this->sourceText, $this->agent, $exception);
    }
}
