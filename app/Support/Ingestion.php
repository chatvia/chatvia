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
dd($url);
        $response = Http::attach('file', $file->getContent(), $file->getFilename())
            ->post($url.'/ingest?agent_id='.$agentFile->agent_id.'&file_id='.$agentFile->id)
            ->timeout(600)
            ->throw()
            ->json();

        $agentFile->update([
            'status' => 'processing',
            'external_ingestion_job_id' => $response['job_id'],
        ]);

        return $response;
    }

    public function cancelJob(AgentFile $agentFile)
    {
        return Http::delete($this->endpoint().'/job/'.$agentFile->external_ingestion_job_id)
            ->throw()
            ->json();
    }
}
