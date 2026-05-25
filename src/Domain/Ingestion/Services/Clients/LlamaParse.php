<?php

namespace Domain\Ingestion\Services\Clients;

use Exception;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\File;

class LlamaParse
{
    private const int SLEEP_SEC = 30;

    private string $baseUrl;

    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.llamaparse.endpoint');
        $this->apiKey = config('services.llamaparse.api_key');
    }

    public function parse(File $file, array $options = []): string
    {
        $defaultOptions = [
            'parse_mode' => 'parse_page_with_llm',
            'high_res_ocr' => true,
            'adaptive_long_table' => true,
            'outlined_table_extraction' => true,
            'output_tables_as_HTML' => true,
            //            'compact_markdown_table' => true,
            //            'ignore_document_elements_for_layout_detection' => true,
            //            'disable_image_extraction' => false,
            //            'extract_charts' => false,
        ];

        $parseOptions = array_merge($defaultOptions, $options);

        return $this->runJob($file, $parseOptions);
    }

    public function getPagesWithImages(string $jobId): array
    {
        $pages = $this->getPages($jobId);
        $pagesWithImages = [];

        foreach ($pages as $page) {
            $images = collect($page['images'])->pluck('name')->toArray();

            $pagesWithImages[] = [
                'text' => $page['text'],
                'images' => $this->getImages($jobId, $images),
            ];
        }

        return $pagesWithImages;

    }

    private function runJob(File $file, array $options): string
    {
        if (! file_exists($file)) {
            throw new RuntimeException("File not found: {$file}");
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
        ])->attach(
            name: 'file',
            contents: $file->getContent(),
            filename: $file->getFilename(),
        )->post("{$this->baseUrl}/upload", $options)
            ->throw()
            ->json();

        if (! isset($response['id'])) {
            throw new RuntimeException('Invalid response: Job ID not found');
        }

        return $response['id'];
    }

    public function getPages(string $jobId): array
    {
        while (true) {
            sleep(self::SLEEP_SEC);

            // First check job status
            $statusResponse = $this->getJobStatus($jobId);

            if (! $statusResponse->successful()) {
                throw new RuntimeException("Failed to check job status: {$statusResponse->body()}");
            }

            $status = $statusResponse->json();
            $jobStatus = $status['status'] ?? '';

            // If job failed, throw an error
            if ($jobStatus === 'FAILED') {
                throw new RuntimeException('LlamaParse job failed: '.($status['error'] ?? 'Unknown error'));
            }

            // If job is not completed yet, continue waiting
            if ($jobStatus !== 'SUCCESS') {
                continue;
            }

            // Job is completed, now try to get the result
            $resultUrl = "{$this->baseUrl}/job/{$jobId}/result/json";
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
            ])->get($resultUrl);

            if ($response->successful()) {
                return $response->json('pages');
            }

            // Handle specific error cases for result fetching
            if ($response->status() === 400) {
                $error = $response->json();
                $errorDetail = $error['detail'] ?? '';

                // If result not found, but job is marked as SUCCESS, wait a bit more
                if (str_contains($errorDetail, 'Result for Parsing Job') && str_contains($errorDetail, 'not found')) {
                    continue;
                }

                if ($errorDetail === 'Job not completed yet') {
                    continue;
                }

                throw new RuntimeException("Parsing error: {$response->body()}");
            }

            throw new RuntimeException("Error fetching job result: {$response->body()}");
        }
    }

    public function getJobStatus(string $jobId)
    {
        return Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
        ])->get("{$this->baseUrl}/job/{$jobId}");
    }

    public function getImages(string $jobId, array $images): array
    {
        $downloadedImages = [];

        // Create images directory if it doesn't exist
        $imagesDir = create_temp_dir();

        foreach ($images as $image) {
            $resultUrl = "{$this->baseUrl}/job/{$jobId}/result/image/{$image}";

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
            ])->get($resultUrl);

            if ($response->successful()) {
                $imageName = "{$jobId}_{$image}";
                $imagePath = "{$imagesDir}/{$imageName}";

                file_put_contents($imagePath, $response->body());

                $downloadedImages[] = [
                    'name' => $imageName,
                    'path' => $imagePath,
                    'size' => strlen($response->body()),
                ];
            } else {
                throw new Exception("Failed to download {$image}: {$response->status()}");
            }
        }

        return $downloadedImages;
    }
}
