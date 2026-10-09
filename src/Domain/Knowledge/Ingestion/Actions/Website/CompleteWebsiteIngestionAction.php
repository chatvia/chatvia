<?php

namespace Domain\Knowledge\Ingestion\Actions\Website;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Jobs\SummaryIngestionJob;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Services\KnowledgeStoragePath;
use Domain\Knowledge\Source\Web\Events\WebsiteCrawlStarted;
use Domain\Knowledge\Source\Web\Events\WebsiteIngestionProgressed;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Finishes a website's training once all its page jobs have run (the `finally`
 * step of the batch, so also after failures).
 *
 * It marks the website as ingested, saves all its chunks as one text file (the
 * document the summary step reads), queues the summary when enhancement is on,
 * and pushes the final state to the Web sources page.
 *
 * `expected_chunks` starts as the number of URLs (a progress placeholder) and
 * each page adds its real chunk count, so the URL count is subtracted here.
 */
class CompleteWebsiteIngestionAction
{
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

        $text = $website->chunks()->pluck('chunk')->implode("\n\n");
        $filePath = KnowledgeStoragePath::forAgent($agent, 'websites/'.$website->id).'/'.Str::uuid()->toString().'.txt';

        Storage::put($filePath, $text);

        $website->update(['filepath' => $filePath]);

        SummaryIngestionJob::dispatchIf(config('chatvia.ai.chunker.enhance') === true, $website);

        WebsiteIngestionProgressed::dispatch($website, $agent->external_id);
        WebsiteCrawlStarted::dispatch($website, $agent->external_id);
    }
}
