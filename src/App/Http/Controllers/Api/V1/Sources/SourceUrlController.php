<?php

namespace App\Http\Controllers\Api\V1\Sources;

use Domain\Agents\Models\Agent;
use Domain\Sources\Web\Data\Crawler\CrawlerData;
use Domain\Sources\Web\Events\WebsiteCrawlerEvent;
use Domain\Sources\Web\Jobs\Crawler\WebsiteCrawlerJob;
use Domain\Sources\Web\Models\SourceWebsite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * @tags Knowledge Sources
 *
 * Crawl a website and ingest its content. The crawler follows links inside
 * the host (subject to include/exclude path rules) and chunks each page.
 */
class SourceUrlController
{
    /**
     * Add URL source
     */
    public function store(Request $request, Agent $agent): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'url'],
            'slow_crawling' => ['sometimes', 'boolean'],
            'include_paths' => ['sometimes', 'nullable', 'string'],
            'exclude_paths' => ['sometimes', 'nullable', 'string'],
        ]);

        $validated['slow_crawling'] = $validated['slow_crawling'] ?? false;

        if ($agent->websites()->where('url', $validated['url'])->exists()) {
            throw ValidationException::withMessages([
                'url' => 'This URL has already been added to this agent.',
            ]);
        }

        $data = CrawlerData::from($validated);

        try {
            $response = Http::withOptions(['allow_redirects' => false])->get($data->url)->throw();
        } catch (Throwable $throwable) {
            report($throwable);
            throw ValidationException::withMessages([
                'url' => 'Failed to fetch the URL. Check that it is reachable.',
            ]);
        }

        if ($response->redirect()) {
            $data->url = $response->header('Location');
        }

        $website = SourceWebsite::create([
            'url' => $data->url,
            'user_id' => $agent->user_id,
            'status' => 'crawling',
            'options' => $data->exclude('url')->toArray(),
        ]);

        $agent->websites()->attach($website->id);

        WebsiteCrawlerEvent::dispatch($website, $agent);
        WebsiteCrawlerJob::dispatch($website, $data, $agent);

        return response()->json([
            'source' => [
                'id' => $website->id,
                'kind' => 'url',
                'name' => $website->url,
                'status' => $website->status,
            ],
        ], 201);
    }

    /**
     * Delete URL source
     */
    public function destroy(Request $request, Agent $agent, int $source): JsonResponse
    {
        /** @var SourceWebsite $website */
        $website = $agent->websites()->where('source_websites.id', $source)->firstOrFail();

        if ($website->internal_chunking_batch_id) {
            $batch = Bus::findBatch($website->internal_chunking_batch_id);

            if ($batch && ! $batch->cancelled()) {
                $batch->cancel();
            }
        }

        $website->chunks()->delete();
        $website->delete();

        return response()->json(null, 204);
    }
}
