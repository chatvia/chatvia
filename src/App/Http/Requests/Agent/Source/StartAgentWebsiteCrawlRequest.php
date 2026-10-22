<?php

namespace App\Http\Requests\Agent\Source;

use Domain\Agent\Rules\SafeWebhookUrl;
use Domain\Knowledge\Source\Web\Data\Crawler\CrawlerData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Validate + shape the seed URL for an agent website crawl. The
 * `agent.sources.train.web` route middleware already gates access, so
 * authorize() is a pass-through. The include/exclude path lists are normalised
 * to bare paths here (a user can paste a full URL) before they reach the
 * CrawlerData DTO, keeping URL-parsing out of the controller.
 */
class StartAgentWebsiteCrawlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'url' => ['required', 'url', new SafeWebhookUrl],
            // Comma-separated lists; an array payload is a 422, never a TypeError.
            'include_paths' => ['nullable', 'string'],
            'exclude_paths' => ['nullable', 'string'],
        ];
    }

    public function toData(): CrawlerData
    {
        $validated = $this->validated();

        if (! empty($validated['include_paths'])) {
            $validated['include_paths'] = $this->sanitizePaths($validated['include_paths']);
        }

        if (! empty($validated['exclude_paths'])) {
            $validated['exclude_paths'] = $this->sanitizePaths($validated['exclude_paths']);
        }

        return CrawlerData::from($validated);
    }

    private function sanitizePaths(string $paths): string
    {
        if (empty($paths)) {
            return $paths;
        }

        $pathList = array_map(trim(...), explode(',', $paths));
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
            // parse_url() never throws; it returns false for a malformed URL
            // and null when there is no path.
            $path = parse_url($input, PHP_URL_PATH);

            return Str::ltrim((string) $path, '/');
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
