<?php

namespace Domain\Knowledge\Ingestion\Actions\Sources;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Pipeline\DispatchSourceIngestionBatchAction;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Ingestion\Jobs\Text\TextChunkIngestionJob;
use Domain\Knowledge\Ingestion\Services\TokenSplitter;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Text\Events\TextIngestionStarted;
use Domain\Knowledge\Source\Text\Models\SourceText;

/**
 * Ingest a text source: split the stored text into token-sized parts and chunk
 * each part in one batch. The Text sources page is told when ingestion starts
 * and, by the batch's completion step, when it ends.
 */
class IngestTextAction
{
    public function __construct(
        private readonly TokenSplitter $splitter,
        private readonly DispatchSourceIngestionBatchAction $dispatchBatch,
    ) {}

    public function execute(SourceText $sourceText, Agent $agent): void
    {
        $startTime = microtime(true);

        $sourceText->update(['status' => SourceStatus::Processing]);
        TextIngestionStarted::dispatch($sourceText, $agent->external_id);

        $jobs = collect($this->splitter->split($sourceText->readContents()))
            ->map(fn (PageData $page): TextChunkIngestionJob => new TextChunkIngestionJob($agent, $sourceText, $page));

        $this->dispatchBatch->execute($sourceText, $agent, $jobs);

        $sourceText->update(['took_in_seconds' => (int) ceil(microtime(true) - $startTime)]);
    }
}
