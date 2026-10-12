<?php

namespace Domain\Knowledge\Ingestion\Data\Ocr;

use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

/**
 * One page of a parsed document, or one part of a split text: the text, its
 * checksum (pages with the same checksum make one chunk), and — from the OCR
 * parser — the page's images (base64), Markdown tables and links. Serialized
 * in queued chunk jobs and cached parser results, so its class name and
 * properties must stay stable.
 */
final class PageData extends Data
{
    /**
     * @param  list<string>  $imagesBase64  the page's images, base64 encoded
     * @param  list<string>  $imagePaths
     * @param  list<string>  $tables  the page's tables as Markdown
     * @param  list<mixed>  $hyperlinks
     */
    public function __construct(
        public int $pageNumber,
        public string $content,
        public string $checksum,
        public int $tokens = 0,
        public array $imagesBase64 = [],
        public array $imagePaths = [],
        public array $tables = [],
        public array $hyperlinks = [],
    ) {}

    /**
     * Pages stored as JSON by the document ingestion (`parser_filepath`).
     *
     * @return Collection<int, self>
     */
    public static function fromJson(string $json): Collection
    {
        /** @var list<array<string, mixed>> $pages */
        $pages = json_decode($json, true);

        return collect($pages)->map(fn ($page): PageData => new PageData(...$page));
    }

    /** The same page without its images, tables and links. */
    public function withoutMedia(): self
    {
        return new self(
            pageNumber: $this->pageNumber,
            content: $this->content,
            checksum: $this->checksum,
            tokens: $this->tokens,
        );
    }
}
