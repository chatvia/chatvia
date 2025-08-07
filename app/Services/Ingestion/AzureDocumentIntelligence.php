<?php

namespace App\Services\Ingestion;

use App\Models\AgentFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AzureDocumentIntelligence
{
    private string $endpoint;

    private string $apiKey;

    public function __construct()
    {
        $this->endpoint = rtrim(config('services.azure_document_intelligence.endpoint'), '/');
        $this->apiKey = config('services.azure_document_intelligence.api_key');
    }

    public function analyzeDocument(string $documentUrl, string $modelId = 'prebuilt-read', array $options = []): string
    {
        $url = "{$this->endpoint}/documentintelligence/documentModels/{$modelId}:analyze";

        $queryParams = array_merge([
            '_overload' => 'analyzeDocument',
            'outputContentFormat' => 'markdown',
            'api-version' => '2024-11-30',
        ], $options);

        $endpoint = $url.'?'.http_build_query($queryParams);

        $response = Http::withHeaders([
            'Ocp-Apim-Subscription-Key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])
            ->timeout(600)
            ->post($endpoint, [
                'urlSource' => $documentUrl,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Azure Document Intelligence API error: '.$response->status().' - '.$response->body()
            );
        }

        return $response->header('Operation-Location');
    }

    public function getAnalysisResult(string|AgentFile $operationLocation): array
    {
        if ($operationLocation instanceof AgentFile) {
            $operationLocation = $operationLocation->azure_operation_location;
        }

        return Http::withHeaders([
            'Ocp-Apim-Subscription-Key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])
            ->timeout(600)
            ->get($operationLocation)
            ->json();
    }
}
