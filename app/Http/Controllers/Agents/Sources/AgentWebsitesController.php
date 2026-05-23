<?php

namespace App\Http\Controllers\Agents\Sources;

use App\Data\Crawler\CrawlerData;
use App\Events\WebsiteCrawlerEvent;
use App\Jobs\Crawler\WebsiteCrawlerJob;
use App\Models\Agent;
use App\Models\SourceWebsite;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

class AgentWebsitesController
{
    public function index(Request $request, Agent $agent): Collection
    {
        abort_if($request->user()->cannot('view', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.sources.train.web'), 403);

        $websites = $agent
            ->websites()
            ->select(['source_websites.id', 'url', 'status', 'source_websites.created_at', 'options', 'expected_chunks'])
            ->withCount('urls')
            ->withCount('agents')
            ->get();

        return collect($websites)->map(function ($website) {
            $website['progress'] = $website->ingestionProgress();

            return $website;
        });
    }

    public function store(Request $request, Agent $agent): void
    {
        abort_if($request->user()->cannot('view', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.sources.train.web'), 403);

        $validated = $request->validate([
            'url' => ['required', 'url'],
            'slow_crawling' => ['required', 'boolean'],
            'include_paths' => ['nullable'],
            'exclude_paths' => ['nullable'],
        ]);

        if ($agent->websites()->where('url', $validated['url'])->exists()) {
            throw ValidationException::withMessages([
                'url' => 'This website has already been added to this agent.',
            ]);
        }

        if (! empty($validated['include_paths'])) {
            $validated['include_paths'] = $this->sanitizePaths($validated['include_paths']);
        }

        if (! empty($validated['exclude_paths'])) {
            $validated['exclude_paths'] = $this->sanitizePaths($validated['exclude_paths']);
        }

        $data = CrawlerData::from($validated);

        try {
            $response = Http::withOptions(['allow_redirects' => false])->get($data->url)->throw();
        } catch (Throwable $throwable) {
            report($throwable);
            throw ValidationException::withMessages([
                'url' => 'Failed to fetch the website. Please check the URL and try again.',
            ]);
        }

        if ($response->redirect()) {
            $data->url = $response->header('Location');
        }

        $website = SourceWebsite::create([
            'url' => $data->url,
            'user_id' => $request->user()->id,
            'status' => 'crawling',
            'options' => $data->exclude('url')->toArray(),
        ]);

        $agent->websites()->attach($website->id);

        WebsiteCrawlerEvent::dispatch($website, $agent);
        WebsiteCrawlerJob::dispatch($website, $data, $agent);
    }

    public function destroy(Request $request, Agent $agent, SourceWebsite $website): void
    {
        abort_if($request->user()->cannot('delete', $website), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.sources.delete'), 403);

        if ($website->internal_chunking_batch_id) {
            $batch = Bus::findBatch($website->internal_chunking_batch_id);

            if ($batch && ! $batch->cancelled()) {
                $batch->cancel();
            }
        }

        $website->chunks()->delete();
        $website->delete();
    }

    public function train(Request $request, Agent $agent, SourceWebsite $website): RedirectResponse
    {
        abort_if($request->user()->cannot('update', $website), 404);

        $validated = $request->validate([
            'recrawl' => ['nullable', 'boolean'],
        ]);

        if ($validated['recrawl'] ?? false) {
            $website->startCrawling($agent->id);
        } else {
            $website->startTraining($agent->id);
        }

        return back()->with('success', 'Training started successfully');
    }

    private function sanitizePaths(string $paths): string
    {
        if (empty($paths)) {
            return $paths;
        }

        $pathList = array_map('trim', explode(',', $paths));
        $sanitized = [];

        foreach ($pathList as $path) {
            if (empty($path)) {
                continue;
            }

            $cleaned = $this->extractPathFromUrl($path);
            if (! empty($cleaned)) {
                $sanitized[] = $cleaned;
            }
        }

        return implode(', ', $sanitized);
    }

    private function extractPathFromUrl(string $input): string
    {
        if (preg_match('/^https?:\/\//', $input)) {
            try {
                $parsed = parse_url($input);

                return ltrim($parsed['path'] ?? '', '/');
            } catch (Exception) {
                return ltrim($input, '/');
            }
        }

        if (preg_match('/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}\//', $input)) {
            $slashPos = strpos($input, '/');
            if ($slashPos !== false) {
                return ltrim(substr($input, $slashPos + 1), '/');
            }
        }

        return ltrim($input, '/');
    }
}
