<?php

namespace Domain\Knowledge\Ingestion\Jobs\Text;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Sources\IngestTextAction;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;

class TextIngestionJob implements ShouldQueue
{
    use Batchable, \Illuminate\Foundation\Queue\Queueable;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public SourceText $sourceText, public Agent $agent)
    {
        $this->onQueue('ingestion.text');
    }

    public function handle(IngestTextAction $action): void
    {
        $action->execute($this->sourceText, $this->agent);
    }
}
