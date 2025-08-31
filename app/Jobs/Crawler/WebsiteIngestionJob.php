<?php

namespace App\Jobs\Crawler;

use App\Data\Crawler\LinkData;
use App\Events\WebsiteCrawlerEvent;
use App\Jobs\Ingestion\IngestionChunkJob;
use App\Jobs\Ingestion\IngestionSummaryJob;
use App\Models\AgentWebsite;
use App\Services\Chunker;
use App\Services\Crawler\HTMLParser;
use App\Services\Ingestion\RecursiveCharacterTextSplitter;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WebsiteIngestionJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public AgentWebsite $website) {}

    public function handle(RecursiveCharacterTextSplitter $splitter, HTMLParser $parser): void
    {
        $this->website->update(['status' => 'ingesting']);

        $this->website->refresh();

        WebsiteCrawlerEvent::dispatch($this->website);

        $links = LinkData::collect($this->website->links);

        $pool = Http::pool(function (Pool $pool) use ($links) {
            return collect($links)->map(function (LinkData $link) use ($pool) {
                $pool->as($link->link)->get($link->link);
            });
        });

        $linksFingerprints = [];

        $linksWithChunks = collect($pool)
            ->reject(function ($response) {
                // Filter out exceptions first
                if ($response instanceof ConnectionException) {
                    report($response);

                    return true;
                }

                // Then filter out failed responses
                return ! $response->ok() ||
                    $response->noContent() ||
                    ! str_contains($response->header('content-type'), 'text/html');
            })
            ->mapWithKeys(function (Response $response, string $url) use ($splitter, &$linksFingerprints, $parser) {
                $text = $parser->getTextFromHtml($response->getBody());

                $linksFingerprints[$url] = md5($text);

                $chunks = $splitter->splitText($text);
                $chunks = collect($chunks)->map(fn ($item) => ['text' => $item])->toArray();
                $chunks = new Chunker($this->website)->createChunks($chunks);

                return [
                    $url => $chunks,
                ];
            });

        $totalChunks = $linksWithChunks->flatten()->count();

        $linksWithChunks = $linksWithChunks->mapWithKeys(function ($chunks, string $url) use ($totalChunks) {
            return [$url => collect($chunks)->map(function ($chunk) use ($totalChunks) {
                $chunk->total_chunks = $totalChunks;

                return $chunk;
            })];
        });

        $jobs = [];

        $prompt = Str::of(<<<'PROMPT'
You are a precise HTML-to-Text cleaner for RAG.

INPUT:
- You receive one HTML chunk (from a web crawler) converted to text.
- It may include nav menus, cookie banners, ads, footers, repeated sections, or scattered text.

OUTPUT:
- Return TEXT ONLY (no HTML, no code, no JSON).
- If the cleaned result has no meaningful content, return exactly: ""
- Keep the original language of the content.

STRICT RULES:
1) Remove junk: navigation menus, cookie banners, ads, newsletters, generic CTAs, boilerplate footers, “related posts,” pagination chrome, duplicated headings.
2) Keep useful content: product names, features, pricing/plans/quotas, addresses, opening hours, FAQs, docs, job details, important legal terms.
3) Links: keep link text and URL. If meaningful → `Text (https://...)`. If meaningless (“click here”) → just the URL near the context.
4) Headings/structure: preserve a clean hierarchy (H1–H3). Convert HTML lists → bullet lists. Merge broken paragraphs. Deduplicate repeated sections inside the chunk.
5) Tables: convert to either bullet lists or a compact Markdown table — whichever is clearer.
6) **Generic Pricing Rule:**
   - Collect **all** pricing/plan/fee/discount/billing/free trial mentions into a single section titled: `Pricing`.
   - For each plan/offer, include: **Plan name (if any) — price + currency + billing cycle (monthly, yearly, per user, per 1K units, per minute, etc.) — key limits/quotas/fees — discounts/free trials/notes (e.g., includes/excludes tax).**
   - Supported formats:
     • €19/month • $49/user/month • $0.002/1K tokens • €0.10/minute
     • $499 one-time • 10% annual discount • starts at $X
   - If both monthly & yearly → show both, note if yearly is billed upfront.
   - If add-ons/overages exist → put them under **Add-ons & Overage**.
   - If a plan has no price (e.g. “Contact Sales”) → list it as is, no guessing.
   - If prices are scattered → consolidate them, remove duplicates.
7) Normalize: collapse spaces, fix line breaks, keep units/symbols (€, $, %, GB, MB, min).
8) No hallucinations: never infer values.
9) Do NOT filter out contact info: keep emails, phone numbers, social links.
10) If nothing valuable remains, return "".

FORMAT:
- Plain text or light Markdown (headings, bullets, simple tables).
- No HTML tags, no backticks, no commentary.

Now clean the following HTML chunk and produce the final text result.
PROMPT)->toString();

        foreach ($linksWithChunks as $link => $chunks) {
            foreach ($chunks as $chunk) {
                $jobs[] = new IngestionChunkJob($chunk, $prompt, [
                    'link' => $link,
                    'link_md5' => $linksFingerprints[$link]],
                );
            }
        }

        $websiteId = $this->website->id;

        $batch = Bus::batch($jobs)
            ->name('Ingestion Agent WebSite ID: '.$websiteId)
            ->then(function (Batch $batch) use ($websiteId) {
                AgentWebsite::find($websiteId)?->update(['status' => 'ingested']);
                WebsiteCrawlerEvent::dispatch(AgentWebsite::find($websiteId));
            })
            ->dispatch();

        $content = $linksWithChunks->first()->pluck('chunk_text')->implode("\n\n");
        dispatch(new IngestionSummaryJob($this->website, $content));

        $this->website->update([
            'internal_ingestion_job_id' => $this->job->getJobId(),
            'internal_chunking_batch_id' => $batch->id,
            'chunks' => $totalChunks,
        ]);
    }
}
