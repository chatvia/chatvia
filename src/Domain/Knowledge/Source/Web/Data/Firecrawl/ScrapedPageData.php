<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\Web\Data\Firecrawl;

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
                return mb_substr(trim($title), 0, 500);
            }
        }

        return null;
    }
}
