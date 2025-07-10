<?php

namespace App\Support;

use App\Models\AgentFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\File;

class Ingestion
{
    public function ingest(AgentFile $agentFile): array
    {
        $url = rtrim(config('services.ingestion.api_endpoint'), '/');
        $file = $agentFile->readFile();
        return Http::attach('file', $file->getContent() , $file->getFilename())
            ->post($url.'/ingest?agent_id='.$agentFile->agent_id.'&file_id='.$agentFile->id)
            ->throw()
            ->json();
    }
}
