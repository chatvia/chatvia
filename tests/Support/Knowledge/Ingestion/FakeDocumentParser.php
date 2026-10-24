<?php

declare(strict_types=1);

namespace Tests\Support\Knowledge\Ingestion;

use Domain\Knowledge\Ingestion\Contracts\DocumentParser;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Illuminate\Support\Collection;

/**
 * Offline stand-in for a document reader (Mistral OCR, Azure, the local
 * reader): every file reads as the same pages, so ingestion tests never reach
 * a parsing service. It counts its calls and keeps the storage folder of each,
 * and its name is what ingestion records as the file's `parser`.
 */
final class FakeDocumentParser implements DocumentParser
{
    public int $calls = 0;

    /** @var list<string> the storage folder of every call */
    public array $directories = [];

    /**
     * @param  list<PageData>  $pages
     */
    public function __construct(
        private readonly array $pages = [],
        private readonly string $name = 'fake',
    ) {}

    /** A reader named $name that reads every file as pages with these texts, page 1 first. */
    public static function reading(string $name, string ...$contents): self
    {
        return new self(array_map(
            static fn (string $content, int $index): PageData => new PageData(pageNumber: $index + 1, content: $content, checksum: hash('xxh128', $content)),
            array_values($contents),
            array_keys(array_values($contents)),
        ), $name);
    }

    public function parse(string $filePath, string $storageDirectory): Collection
    {
        $this->calls++;
        $this->directories[] = $storageDirectory;

        return collect($this->pages);
    }

    public function name(): string
    {
        return $this->name;
    }
}
