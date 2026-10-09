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
}
