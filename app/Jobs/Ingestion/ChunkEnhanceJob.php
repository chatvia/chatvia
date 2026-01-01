<?php

namespace App\Jobs\Ingestion;

use App\Models\Agent;
use App\Models\SourceFile;
use App\Models\SourceText;
use App\Models\SourceWebsite;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ChunkEnhanceJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public int $timeout = 3600;

    public function __construct(public Agent $agent, public SourceFile|SourceText|SourceWebsite $source)
    {
    }

    public function handle(): void
    {
        if ($this->source::class === SourceWebsite::class)
        {
            $this->enhanceWebsite();
        }
    }

    private function enhanceWebsite()
    {

    }

    public function uniqueId()
    {
        return 'agents:'.$this->agent->id.':sources:'.$this->source::class.':'.$this->source->id;
    }
}
