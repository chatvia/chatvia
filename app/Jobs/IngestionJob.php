<?php

namespace App\Jobs;

use App\Data\Chunk;
use App\Models\AgentFile;
use App\Models\Document;
use App\Support\AzureDocumentIntelligence;
use App\Support\Cohere;
use App\Support\AzureMarkdownSplitter;
use App\Support\OpenRouter;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class IngestionJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public $tries = 1;

    public $timeout = 1800;

    public function __construct(public AgentFile $agentFile)
    {

    }

    public function handle(
        AzureDocumentIntelligence $azureDocumentIntelligence,
    ): void
    {
        $startTime = microtime(true);

        $operationLocation = $this->agentFile->azure_operation_location;
        $jobId = $this->job->getJobId();
        $updateValues = [
            'internal_job_id' => $jobId ?: null,
        ];

        if (!$operationLocation) {
            $operationLocation = $azureDocumentIntelligence->analyzeDocument($this->agentFile->tempUrl());
            $updateValues['azure_operation_location'] = $operationLocation;
        }

        $this->agentFile->update($updateValues);

        $content = Cache::get(key: md5($this->agentFile->azure_operation_location));

        $maxWaitTime = 900; // 15 minutes max
        $startWaitTime = time();

        while (empty($content)) {
            // Check if we've been waiting too long
            if (time() - $startWaitTime > $maxWaitTime) {
                throw new \Exception("Azure Document Intelligence processing timed out after {$maxWaitTime} seconds");
            }

            $result = $azureDocumentIntelligence->getAnalysisResult($operationLocation);

            if (isset($result['status']) && $result['status'] === 'succeeded') {
                $content = $result['analyzeResult']['content'];

                file_put_contents('1.json', json_encode($result, JSON_PRETTY_PRINT));

                Cache::put(
                    key: md5($this->agentFile->azure_operation_location),
                    value: $content,
                    ttl: now()->addHours(3)
                );

                break;
            }

            if (isset($result['status']) && $result['status'] === 'failed') {
                throw new \Exception("Azure Document Intelligence processing failed: " . json_encode($result));
            }

            sleep(5);
        }

        $jsonDocCacheId = 'json_doc'. $this->agentFile->id;

//        $jsonDoc = Cache::get($jsonDocCacheId);

        $this->ingest($content);

        //        if (!$jsonDoc) {
//        }

//        $filePath = storage_path('tmp/'.$jsonDocCacheId.'.json');
//
//        if (!file_exists($filePath)) {
//            file_put_contents(
//                $filePath, $jsonDoc
//            );
//        }
//
//        Cache::put($jsonDocCacheId, 'true', now()->addHours(2));

//        DB::statement("COPY public.documents (agent_id, agent_file_id, type, original, enhanced, md5_hash, embedding, metadata) FROM '{$filePath}' CSV QUOTE E'\b' DELIMITER E'\t' NULL AS '' ENCODING 'UTF8'");


//        $this->agentFile->ingest();
//
//        $processingTime = round(microtime(true) - $startTime);
//
//        $this->agentFile->update([
//            'status' => 'completed',
//            'ingested_doc_processing_time_in_sec' => $processingTime,
//        ]);
    }

    private function ingest($content)
    {
        $markdownSplitter = app(AzureMarkdownSplitter::class);
        $openRouter = app(OpenRouter::class);
        $cohere = app(Cohere::class);

        $splitter = $markdownSplitter->split($content);
        $chunksInGroups = collect($splitter)->chunk(10);

        foreach ($chunksInGroups as $group) {
            $bulkInsertion = [];

            $enhancement = $openRouter->bulkEnhance($group);
            $chunksWithEmbeddings = $cohere->bulkEmbed($enhancement);

            /** @var Chunk $chunk */
            foreach ($chunksWithEmbeddings as $chunk) {
                if (Document::query()->where('md5_hash', md5($chunk->original_content))->where('agent_id', $this->agentFile->agent_id)->exists()) {
                    continue;
                }

                $bulkInsertion[] = [
                    'agent_id' => $this->agentFile->agent->id,
                    'agent_file_id' => $this->agentFile->id,
                    'type' => 'text',
                    'original' => $chunk->original_content,
                    'enhanced' => $chunk->enhanced_content,
                    'md5_hash' => md5($chunk->original_content),
                    'embedding' => '[' . implode(',', $chunk->embedding) . ']',
                    'metadata' => json_encode($chunk->metadata),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            DB::table('documents')->insert($bulkInsertion);
        }
    }

    public function uniqueId(): string
    {
        return $this->agentFile->id;
    }

    public function tags(): array
    {
        return [
            'agent_file:'. $this->agentFile->id,
        ];
    }

    public function failed(\Throwable $exception): void
    {
        // Update the agent file status to failed when the job fails
        $this->agentFile->update([
            'status' => 'failed',
            'failure_reason' => $exception->getMessage(),
        ]);
    }
}
