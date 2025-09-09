<?php

namespace App\Jobs;

use App\Models\AgentText;
use App\Services\Chunker;
use App\Services\Ingestion\RecursiveCharacterTextSplitter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TextIngestionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public AgentText $text)
    {
    }

    public function handle(RecursiveCharacterTextSplitter $splitter): void
    {
        $startTime = microtime(true);

        $texts = collect($splitter->splitText($this->text->text))
            ->map(fn ($text) => ['text' => $text])
            ->toArray();

        $chunker = new Chunker($this->text)->createChunks($texts);

        $this->text->update(['status' => 'ingesting']);

        $this->text->refresh();
    }
}
