<?php

namespace Domain\Knowledge\Ingestion\Actions\Sources;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Data\OCR\PageData;
use Domain\Knowledge\Ingestion\Jobs\SummaryIngestionJob;
use Domain\Knowledge\Ingestion\Jobs\Text\TextChunkIngestionJob;
use Domain\Knowledge\Ingestion\Services\TokenSplitter;
use Domain\Knowledge\Source\Text\Events\TextIngestionStarted;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

class IngestTextAction
{
    public function __construct(
        private readonly TokenSplitter $splitter,
    ) {}

    public function execute(SourceText $sourceText, Agent $agent): void
    {
        $startTime = microtime(true);

        $sourceText->update(['status' => 'ingesting']);
        TextIngestionStarted::dispatch($sourceText, $agent);

        $file = $sourceText->readFile();

        $texts = collect($this->splitter->split($file->getContent()));

        $jobs = collect($texts)->map(fn (PageData $page): TextChunkIngestionJob => new TextChunkIngestionJob($agent, $sourceText, $page));

        dispatch(new SummaryIngestionJob($sourceText));

        $agentTextId = $sourceText->id;

        $batch = Bus::batch($jobs)
            ->name('Ingestion Agent Text ID: '.$sourceText->id)
            ->then(function (Batch $batch) use ($agentTextId, $agent): void {
                SourceText::find($agentTextId)?->update(['status' => 'ingested']);
                TextIngestionStarted::dispatch(SourceText::find($agentTextId), $agent);

                DB::statement('ANALYZE chunks');
            })
            ->dispatch();

        $endTime = microtime(true);

        $tookInSeconds = ceil($endTime - $startTime);

        $sourceText->update([
            'expected_chunks' => count($texts),
            'internal_ingestion_job_id' => $batch->id,
            'took_in_seconds' => $tookInSeconds,
        ]);

        $sourceText->refresh();
    }
}
