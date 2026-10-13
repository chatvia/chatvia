<?php

namespace Domain\Knowledge\Ingestion\Actions\Sources;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Pipeline\DispatchSourceIngestionBatchAction;
use Domain\Knowledge\Ingestion\Contracts\TextSplitter;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Ingestion\Enums\ContentKind;
use Domain\Knowledge\Ingestion\Jobs\Text\TextChunkIngestionJob;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Text\Events\TextIngestionStarted;
use Domain\Knowledge\Source\Text\Models\SourceText;

/**
 * Ingest a text source: split the stored text with the text splitter (as
 * plain text) and chunk the parts in one batch. The Text sources page is told when
 * ingestion starts and, by the batch's completion step, when it ends.
 */
class IngestTextAction
{
    public function __construct(
        private readonly TextSplitter $splitter,
        private readonly DispatchSourceIngestionBatchAction $dispatchBatch,
    ) {}

    public function execute(SourceText $sourceText, Agent $agent): void
    {
        $startTime = microtime(true);

        $sourceText->update(['status' => SourceStatus::Processing]);
        TextIngestionStarted::dispatch($sourceText, $agent->external_id);

        $parts = $this->splitter->split($sourceText->readContents(), ContentKind::Text);

        $jobs = TextChunkIngestionJob::forPages($agent, $sourceText, collect(PageData::fromParts($parts)));

        $this->dispatchBatch->execute($sourceText, $agent, $jobs);

        $sourceText->update(['took_in_seconds' => (int) ceil(microtime(true) - $startTime)]);
    }
}
