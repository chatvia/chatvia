<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\Web\Data\Firecrawl;

use Illuminate\Support\Str;
use Spatie\LaravelData\Data;

/**
 * One page as FireCrawl's `/scrape` returned it: the main content as Markdown
 * and the page metadata FireCrawl found (title, language, status code, …).
 */
final class ScrapedPageData extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $markdown,
        public array $metadata = [],
    ) {}

    /** The page's title (its `<title>`, else its Open Graph title), trimmed; null when it has none. */
    public function title(): ?string
    {
        foreach (['title', 'ogTitle'] as $key) {
            $title = $this->metadata[$key] ?? null;

            if (filled($title)) {
                return Str::substr(Str::trim($title), 0, 500);
            }
        }

        return null;
    }

    /** The HTTP status the site answered with; null when FireCrawl did not report one. */
    public function statusCode(): ?int
    {
        $status = $this->metadata['statusCode'] ?? null;

        return is_numeric($status) ? (int) $status : null;
    }

    /** True when the site answered with an error (4xx, 5xx): the Markdown is its error page, not content. */
    public function isError(): bool
    {
        return ($this->statusCode() ?? 200) >= 400;
    }
}
