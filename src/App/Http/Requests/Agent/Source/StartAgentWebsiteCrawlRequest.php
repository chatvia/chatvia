<?php

namespace App\Http\Requests\Agent\Source;

use Domain\Agent\Rules\SafeWebhookUrl;
use Domain\Knowledge\Source\Web\Data\Crawler\CrawlerData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Support\Http\SafeUrl;

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

        return Str::of($paths)
            ->explode(',')
            ->map(static fn (string $path): string => Str::trim($path))
            ->filter()
            ->map(fn (string $path): string => $this->extractPathFromUrl($path))
            ->filter()
            ->implode(', ');
    }

    private function extractPathFromUrl(string $input): string
    {
        if (Str::isMatch('/^https?:\/\//', $input)) {
            // The raw path, so a trailing slash stays part of the pattern;
            // a malformed URL has no path.
            $path = SafeUrl::parse($input)?->getUri()->getPath() ?? '';

            return Str::ltrim($path, '/');
        }

        if (Str::isMatch('/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}\//', $input)) {
            return Str::ltrim(Str::after($input, '/'), '/');
        }

        return Str::ltrim($input, '/');
    }
}
