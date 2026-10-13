<?php

namespace Domain\Knowledge\Ingestion\Actions\Website;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Enums\ChunkEnhancement;
use Domain\Knowledge\Ingestion\Jobs\SummaryIngestionJob;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Services\KnowledgeStoragePath;
use Domain\Knowledge\Source\Web\Events\WebsiteCrawlStarted;
use Domain\Knowledge\Source\Web\Events\WebsiteIngestionProgressed;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Finishes a website's training once all its page jobs have run (the `finally`
 * step of the batch, so also after failures).
 *
 * It marks the website as ingested, saves all its page chunks as one text file
 * in the website's storage folder (the document the summary step reads; the
 * previous document of a retrain is deleted), queues the summary when
 * enhancement is on, and pushes the final state to the Web sources page.
 *
 * `expected_chunks` starts as the number of URLs (a progress placeholder) and
 * each page adds its real chunk count, so the URL count is subtracted here.
 */
class CompleteWebsiteIngestionAction
{
    /** Chunks read per query while the document is written. */
    private const int CHUNKS_PER_READ = 500;

    public function execute(SourceWebsite $website, Agent $agent): void
    {
        DB::transaction(function () use ($website): void {
            $locked = SourceWebsite::query()->whereKey($website->id)->lockForUpdate()->first();

            if ($locked === null) {
                return;
            }

            $locked->update(['status' => SourceStatus::Completed]);
            $locked->decrement('expected_chunks', $locked->urls()->count());
        });

        $website->refresh();

        $previousPath = $website->filepath;
        $filePath = KnowledgeStoragePath::forSource($website).'/'.Str::uuid()->toString().'.txt';

        $this->writeDocument($website, $filePath);

        $website->update(['filepath' => $filePath]);

        if ($previousPath !== null && $previousPath !== $filePath) {
            Storage::delete($previousPath);
        }

        SummaryIngestionJob::dispatchIf(ChunkEnhancement::for($website)->summarizes(), $website);

        WebsiteIngestionProgressed::dispatch($website, $agent->external_id);
        WebsiteCrawlStarted::dispatch($website, $agent->external_id);
    }

    /**
     * Writes the website's page chunks to storage through a temporary stream,
     * so a large website is never held in memory at once. The chunks are read
     * in batches in `chunk_index` order, with a keyset on (chunk_index, id)
     * that the (chunkable, chunk_index) index serves, so every batch costs the
     * same however far into the website it is.
     */
    private function writeDocument(SourceWebsite $website, string $filePath): void
    {
        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            throw new RuntimeException('Could not open a temporary stream for the website document.');
        }

        try {
            $separator = '';
            $after = [0, 0];

            do {
                $chunks = $website->chunks()
                    ->whereRaw('(chunk_index, id) > (?, ?)', $after)
                    ->orderBy('chunk_index')
                    ->orderBy('id')
                    ->limit(self::CHUNKS_PER_READ)
                    ->get(['id', 'chunk_index', 'chunk']);

                foreach ($chunks as $chunk) {
                    fwrite($stream, $separator.$chunk->chunk);
                    $separator = "\n\n";
                    $after = [$chunk->chunk_index, $chunk->id];
                }
            } while ($chunks->count() === self::CHUNKS_PER_READ);

            rewind($stream);
            Storage::writeStream($filePath, $stream);
        } finally {
            fclose($stream);
        }
    }
}
