<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Data\Chunking;

use Spatie\LaravelData\Data;

/**
 * Prose descriptions of the images and tables on one document page. They are
 * added to the chunk's embedding text so the page can be found by what its
 * images and tables show, and kept in the chunk's metadata: the image paths let
 * the chat show an image next to the answer.
 */
final class PageVisualsData extends Data
{
    /**
     * @param  array<string, string>  $imageDescriptions  storage path of the saved image => its description
     * @param  list<string>  $tables  the page's non-empty Markdown tables
     * @param  string  $tableDescription  one prose description of all tables
     */
    public function __construct(
        public array $imageDescriptions,
        public array $tables,
        public string $tableDescription,
    ) {}

    /**
     * The descriptions to add to the page text before embedding, in reading
     * order (images first, then tables).
     */
    public function embeddingText(): string
    {
        $parts = array_values($this->imageDescriptions);

        if ($this->tableDescription !== '') {
            $parts[] = $this->tableDescription;
        }

        return implode("\n\n", $parts);
    }

    /**
     * The chunk `metadata` shape the chat and the image route read.
     *
     * @return array{images: array<string, string>, tables: array{markdown: list<string>, descriptions: string}}
     */
    public function toMetadata(): array
    {
        return [
            'images' => $this->imageDescriptions,
            'tables' => [
                'markdown' => $this->tables,
                'descriptions' => $this->tableDescription,
            ],
        ];
    }
}
