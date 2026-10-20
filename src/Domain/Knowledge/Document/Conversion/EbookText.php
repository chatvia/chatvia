<?php

declare(strict_types=1);

namespace Domain\Knowledge\Document\Conversion;

use Domain\Knowledge\Document\Conversion\Markdown\MarkdownReaders;
use InvalidArgumentException;
use Support\Executable;

/**
 * Reads the text of an ebook (EPUB, FictionBook) as Markdown, so the text
 * splitter has its headings. No program is needed on the server:
 *
 *  - EPUB → pandoc when it is installed, otherwise the built-in reader
 *  - FB2  → always the built-in reader: pandoc reads a windows-1251 book
 *           (common for Russian books) as Latin-1 and drops FB2 tables
 *
 * The built-in readers matched pandoc on a set of sample books
 * (tests/Eval/DocumentReaderEvalTest.php).
 */
class EbookText
{
    private const array EXTENSIONS = ['epub', 'fb2'];

    public function __construct(
        private readonly Pandoc $pandoc,
        private readonly MarkdownReaders $readers,
    ) {}

    public function supports(string $extension): bool
    {
        return in_array(strtolower($extension), self::EXTENSIONS, true);
    }

    public function toMarkdown(string $path, string $extension): string
    {
        $extension = strtolower($extension);

        if (! $this->supports($extension)) {
            throw new InvalidArgumentException("Unsupported ebook format: {$extension}");
        }

        return $this->usesPandoc($extension)
            ? $this->pandoc->toMarkdown($path, $extension)
            : $this->readers->toMarkdown($path, $extension);
    }

    private function usesPandoc(string $extension): bool
    {
        return $extension === 'epub' && Executable::exists((string) config('services.bin.pandoc'));
    }
}
