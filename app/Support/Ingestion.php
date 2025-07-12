<?php

namespace App\Support;

use App\Models\AgentFile;
use Illuminate\Support\Facades\Http;

class Ingestion
{
    public function endpoint(): string
    {
        return rtrim(config('services.ingestion.api_endpoint'), '/');
    }

    public function ingest(AgentFile $agentFile): array
    {
        $url = rtrim(config('services.ingestion.api_endpoint'), '/');
        $file = $agentFile->isWordFile() ? $agentFile->convertToPdf() : $agentFile->readFile();

        return Http::attach('file', $file->getContent(), $file->getFilename())
            ->post($url.'/ingest?agent_id='.$agentFile->agent_id.'&file_id='.$agentFile->id)
            ->throw()
            ->json();
    }

    public function cancelJob(AgentFile $agentFile)
    {
        return Http::delete($this->endpoint().'/job/'.$agentFile->external_ingestion_job_id)
            ->throw()
            ->json();
    }
}
