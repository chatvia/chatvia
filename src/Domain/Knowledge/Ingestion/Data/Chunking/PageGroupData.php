<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Data\Chunking;

use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Ingestion\Enums\ChunkEnhancement;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

/**
 * Consecutive pages of one source that one chunk job stores, with the page
 * just before and just after them (context when a document page is
 * enhanced). Serialized in queued chunk jobs.
 */
final class PageGroupData extends Data
{
    /**
     * @param  list<PageData>  $pages
     */
    public function __construct(
        public array $pages,
        public ?PageData $previousPage = null,
        public ?PageData $nextPage = null,
    ) {}

    /**
     * Splits a source's pages, in reading order, into the groups its chunk
     * jobs store. Images and tables travel only when they are described, so
     * the queued payload stays small.
     *
     * @param  Collection<int, PageData>  $pages
     * @return Collection<int, self>
     */
    public static function split(Collection $pages, ChunkEnhancement $enhancement): Collection
    {
        $pages = $pages->values();
        $textOnly = $pages->map(fn (PageData $page): PageData => $page->withoutMedia());
        $stored = $enhancement->needsMedia() ? $pages : $textOnly;

        return $stored
            ->chunk($enhancement->pagesPerJob())
            ->map(fn (Collection $group): self => new self(
                pages: $group->values()->all(),
                previousPage: $textOnly->get((int) $group->keys()->first() - 1),
                nextPage: $textOnly->get((int) $group->keys()->last() + 1),
            ))
            ->values();
    }

    /** The page before the one at $index in this group. */
    public function before(int $index): ?PageData
    {
        return $index === 0 ? $this->previousPage : ($this->pages[$index - 1] ?? null);
    }

    /** The page after the one at $index in this group. */
    public function after(int $index): ?PageData
    {
        return $index === count($this->pages) - 1 ? $this->nextPage : ($this->pages[$index + 1] ?? null);
    }
}
