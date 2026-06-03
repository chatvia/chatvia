<?php

namespace App\Http\Controllers\Agent\Source\Web;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Web\Actions\Crawl\StartAgentWebsiteCrawlAction;
use Domain\Knowledge\Source\Web\Actions\Source\DeleteSourceWebsiteAction;
use Domain\Knowledge\Source\Web\Data\Crawler\CrawlerData;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Membership\Team\Exceptions\InviteRejectedException;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AgentWebsiteController
{
    public function index(Request $request, Agent $agent): Collection
    {
        abort_if($request->user()->cannot('view', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.sources.train.web'), 403);

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
        abort_if(! has_permission_to_agent($agent, 'agent.sources.train.web'), 403);

        $validated = $request->validate([
            'url' => ['required', 'url'],
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

        try {
            $startCrawl->execute($agent, $user, CrawlerData::from($validated));
        } catch (InviteRejectedException $e) {
            throw ValidationException::withMessages([$e->field => $e->getMessage()]);
        }
    }

    public function destroy(Request $request, Agent $agent, SourceWebsite $website, DeleteSourceWebsiteAction $delete): void
    {
        abort_if($request->user()->cannot('delete', $website), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.sources.delete'), 403);

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
