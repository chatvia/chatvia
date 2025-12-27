<?php

namespace App\Jobs\Ingestion;

use App\Data\Ingestion\OCR\PageData;
use App\Events\TextIngestionEvent;
use App\Models\Agent;
use App\Models\SourceText;
use App\Services\Chunking\Chunker;
use App\Services\Splitters\RecursiveCharacterTextSplitter;
use App\Services\TikToken;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

class TextIngestionJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public SourceText $sourceText, public Agent $agent)
    {
        $this->onQueue('ingestion.text');
    }

    public function handle(RecursiveCharacterTextSplitter $splitter): void
    {
        $startTime = microtime(true);

        $content = Storage::get($this->sourceText->filepath);

        if (TikToken::countTokens($content) <= 1000) {
            $texts = collect([
                new PageData(pageNumber: 0, content: $content),
            ]);
        } else {
            $texts = collect($splitter->splitText($content))
                ->map(function ($text) {
                    return new PageData(
                        pageNumber: 0,
                        content: $text,
                    );
                });
        }

        $chunks = new Chunker(
            agent: $this->agent,
            chunkable: $this->sourceText,
        )->createChunks($texts);

        $this->sourceText->update(['status' => 'ingesting']);

        TextIngestionEvent::dispatch($this->sourceText, $this->agent);

        $jobs = collect($chunks)->map(fn ($chunk) => new ChunkIngestionJob($chunk));

        //        $url = '';
        //
        //        dispatch(new SummaryIngestionJob($this->sourceText, $url));

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
            'expected_chunks' => $chunks[0]->expected_chunks,
            'internal_ingestion_job_id' => $batch->id,
            'took_in_seconds' => $tookInSeconds,
        ]);

        $this->sourceText->refresh();
    }
}
