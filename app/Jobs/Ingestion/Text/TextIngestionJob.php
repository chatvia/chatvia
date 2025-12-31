<?php

namespace App\Jobs\Ingestion\Text;

use App\Data\Ingestion\OCR\PageData;
use App\Events\TextIngestionEvent;
use App\Jobs\Ingestion\SummaryIngestionJob;
use App\Models\Agent;
use App\Models\SourceText;
use App\Services\TokenSplitter;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;

class TextIngestionJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public SourceText $sourceText, public Agent $agent)
    {
        $this->onQueue('ingestion.text');
    }

    public function handle(TokenSplitter $splitter): void
    {
        $startTime = microtime(true);

        $this->sourceText->update(['status' => 'ingesting']);
        TextIngestionEvent::dispatch($this->sourceText, $this->agent);

        $file = $this->sourceText->readFile();

        $texts = collect($splitter->split($file->getContent()));

        $jobs = collect($texts)->map(fn (PageData $page) => new TextChunkIngestionJob($this->agent, $this->sourceText, $page));

        dispatch(new SummaryIngestionJob($this->sourceText));

        $agentTextId = $this->sourceText->id;
        $agent = $this->agent;

        $batch = Bus::batch($jobs)
            ->name('Ingestion Agent Text ID: '.$this->sourceText->id)
            ->then(function (Batch $batch) use ($agentTextId, $agent) {
                SourceText::find($agentTextId)?->update(['status' => 'ingested']);
                TextIngestionEvent::dispatch(SourceText::find($agentTextId), $agent);
            })
            ->dispatch();

        $endTime = microtime(true);

        $tookInSeconds = ceil($endTime - $startTime);

        $this->sourceText->update([
            'expected_chunks' => count($texts),
            'internal_ingestion_job_id' => $batch->id,
            'took_in_seconds' => $tookInSeconds,
        ]);

        $this->sourceText->refresh();
    }
}
