<?php

namespace App\Jobs;

use App\Jobs\Ingestion\IngestionChunkJob;
use App\Jobs\Ingestion\IngestionSummaryJob;
use App\Models\AgentText;
use App\Services\Chunker;
use App\Services\Ingestion\RecursiveCharacterTextSplitter;
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

    public function __construct(public AgentText $text) {}

    public function handle(RecursiveCharacterTextSplitter $splitter): void
    {
        // TODO: Edge cases:
        // What if the text is too small? How would that fit in the "ingestion summary"?
        // What is the minium and maximum text length?
        //

        /*
         * If tht text is less than 1000 tokens, then there is no need to chunk it.
         * If the text is more than 1000 tokens, we have to scenarios here:
         *  1.
         */

        $startTime = microtime(true);

        $texts = collect($splitter->splitText($this->text->text))
            ->map(fn ($text) => ['text' => $text])
            ->toArray();

        $chunks = new Chunker($this->text)->createChunks($texts);

        $this->text->update(['status' => 'ingesting']);

        // TODO: send websocket event

        $jobs = collect($chunks)
            ->map(fn ($chunk) => new IngestionChunkJob($chunk));

        $jobs->add(
            new IngestionSummaryJob($this->text, $this->text->text)
        );

        $textId = $this->text->id;

        $batch = Bus::batch($jobs)
            ->name('Ingestion Agent Text ID: '.$this->text->id)
            ->then(function (Batch $batch) use ($textId) {
                AgentText::find($textId)?->update(['status' => 'ingested']);
                // TODO: send websocket event
            })
            ->dispatch();

        $this->text->update([
            'expected_chunks' => $chunks[0]->expected_chunks,
            'internal_ingestion_job_id' => $batch->id,
        ]);

        $this->text->refresh();
    }
}
