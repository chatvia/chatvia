<?php

namespace App\Http\Controllers\Agent\Source\Web;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Web\Actions\Crawl\StartAgentWebsiteCrawlAction;
use Domain\Agent\Rules\SafeWebhookUrl;
use Domain\Knowledge\Source\Web\Actions\Source\DeleteSourceWebsiteAction;
use Domain\Knowledge\Source\Web\Data\Crawler\CrawlerData;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AgentWebsiteController
{
    public function index(Request $request, Agent $agent): Collection
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        $websites = $agent
            ->websites()
            ->select(['source_websites.id', 'url', 'status', 'source_websites.created_at', 'options', 'expected_chunks'])
            ->withCount(['urls', 'agents', 'chunks'])
            ->get();

        return collect($websites)->map(function ($website) {
            $website['progress'] = $website->ingestionProgress();

            return $website;
        });
    }

    public function store(Request $request, Agent $agent, StartAgentWebsiteCrawlAction $startCrawl): void
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        $validated = $request->validate([
            'url' => ['required', 'url', new SafeWebhookUrl],
            'slow_crawling' => ['required', 'boolean'],
            'include_paths' => ['nullable'],
            'exclude_paths' => ['nullable'],
        ]);

        if (! empty($validated['include_paths'])) {
            $validated['include_paths'] = $this->sanitizePaths($validated['include_paths']);
        }

        if (! empty($validated['exclude_paths'])) {
            $validated['exclude_paths'] = $this->sanitizePaths($validated['exclude_paths']);
        }

        /** @var User $user */
        $user = $request->user();

        $startCrawl->execute($agent, $user, CrawlerData::from($validated));
    }

    public function destroy(Request $request, Agent $agent, SourceWebsite $website, DeleteSourceWebsiteAction $delete): void
    {
        abort_if($request->user()->cannot('delete', $website), 404);

        $delete->execute($website);
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
