<?php

namespace App\Jobs\Ingestion;

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

class TextIngestionJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public SourceText $agentText, public int $agentId)
    {
        $this->onQueue('ingestion.text');
    }

    public function handle(RecursiveCharacterTextSplitter $splitter): void
    {
        $startTime = microtime(true);

        if (TikToken::countTokens($this->agentText->content) <= 1000) {
            $texts = [
                ['text' => $this->agentText->content],
            ];
        } else {
            $texts = collect($splitter->splitText($this->agentText->content))
                ->map(fn ($text) => ['text' => $text])
                ->toArray();
        }

        $chunks = new Chunker(
            agent: $this->agentId,
            chunkable: SourceText::class,
            chunkableId: $this->agentText->id,
        )->createChunks($texts);

        $this->agentText->update(['status' => 'ingesting']);

        $agentExternalId = Agent::where('id', $this->agentId)->value('external_id');

        TextIngestionEvent::dispatch($this->agentText, $agentExternalId);

        $jobs = collect($chunks)->map(fn ($chunk) => new ChunkIngestionJob($chunk));

        $jobs->add(
            new SummaryIngestionJob($this->agentText, $this->agentText->content)
        );

        $agentTextId = $this->agentText->id;
        $externalId = $agentExternalId;

        $batch = Bus::batch($jobs)
            ->name('Ingestion Agent Text ID: '.$this->agentText->id)
            ->then(function (Batch $batch) use ($agentTextId, $externalId) {
                SourceText::find($agentTextId)?->update(['status' => 'ingested']);
                TextIngestionEvent::dispatch(SourceText::find($agentTextId), $externalId);
            })
            ->dispatch();

        $endTime = microtime(true);

        $tookInSeconds = ceil($endTime - $startTime);

        $this->agentText->update([
            'expected_chunks' => $chunks[0]->expected_chunks,
            'internal_ingestion_job_id' => $batch->id,
            'took_in_seconds' => $tookInSeconds,
        ]);

        $this->agentText->refresh();
    }
}
