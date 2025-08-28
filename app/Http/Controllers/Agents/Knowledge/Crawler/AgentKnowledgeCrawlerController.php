<?php

namespace App\Http\Controllers\Agents\Knowledge\Crawler;

use App\Data\Crawler\CrawlerData;
use App\Events\CrawlingWebsiteEvent;
use App\Jobs\Crawler\WebsiteCrawlerJob;
use App\Models\Agent;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

class AgentKnowledgeCrawlerController
{
    public function __invoke(Request $request, Agent $agent)
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'unique:agent_websites,url,NULL,id,agent_id,'.$agent->id],
            'slow_crawling' => ['required', 'boolean'],
            'include_paths' => ['nullable'],
            'exclude_paths' => ['nullable'],
        ]);

        // Sanitize include_paths and exclude_paths to remove full URLs and keep only paths
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

        $website = $agent->websites()->create([
            'url' => $data->url,
            'agent_id' => $agent->id,
            'user_id' => $request->user()->id,
            'status' => 'crawling',
            'options' => $data->exclude('url')->toArray(),
        ]);

        CrawlingWebsiteEvent::dispatch($website);

        WebsiteCrawlerJob::dispatch($website, $data);
    }

    /**
     * Sanitize paths by removing full URLs and keeping only the path portion
     */
    private function sanitizePaths(string $paths): string
    {
        if (empty($paths)) {
            return $paths;
        }

        // Split paths by comma, trim whitespace, and process each path
        $pathList = array_map('trim', explode(',', $paths));
        $sanitizedPaths = [];

        foreach ($pathList as $path) {
            if (empty($path)) {
                continue;
            }

            $sanitizedPath = $this->extractPathFromUrl($path);
            if (! empty($sanitizedPath)) {
                $sanitizedPaths[] = $sanitizedPath;
            }
        }

        return implode(', ', $sanitizedPaths);
    }

    /**
     * Extract path portion from a URL or return the path as-is
     */
    private function extractPathFromUrl(string $input): string
    {
        // Case 1: Full URL with protocol (http:// or https://)
        if (preg_match('/^https?:\/\//', $input)) {
            try {
                $parsedUrl = parse_url($input);
                $pathOnly = $parsedUrl['path'] ?? '';

                return ltrim($pathOnly, '/');
            } catch (Exception $e) {
                return ltrim($input, '/');
            }
        }

        // Case 2: URL without protocol but with domain (contains . and /)
        // Examples: www.laravel.com/docs, laravel.com/docs/*, example.org/blog
        if (preg_match('/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}\//', $input)) {
            // Find the first slash after the domain
            $slashPos = strpos($input, '/');
            if ($slashPos !== false) {
                // Extract everything after the first slash
                $pathOnly = substr($input, $slashPos + 1);

                return ltrim($pathOnly, '/');
            }
        }

        // Case 3: Already a path or simple pattern (no domain detected)
        // Examples: blog/*, docs/*, /admin, api/v1
        return ltrim($input, '/');
    }
}
