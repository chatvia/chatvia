<?php

declare(strict_types=1);

namespace Domain\Knowledge\Document\Conversion\Markdown;

use InvalidArgumentException;

/**
 * The built-in readers that turn DOCX, ODT, EPUB and FB2 into GitHub
 * Markdown in PHP, as candidates to stand in for pandoc on hosts that cannot
 * install it. Not used for uploads yet: tests/Eval/DocumentReaderEvalTest.php
 * compares them with pandoc first.
 */
final class MarkdownReaders
{
    private const array EXTENSIONS = ['docx', 'odt', 'epub', 'fb2'];

    public function supports(string $extension): bool
    {
        return in_array(strtolower($extension), self::EXTENSIONS, true);
    }

    /** The file's text as GitHub Markdown, read by the reader for its extension. */
    public function toMarkdown(string $path, string $extension): string
    {
        return match (strtolower($extension)) {
            'docx' => new DocxReader()->toMarkdown($path),
            'odt' => new OdtReader()->toMarkdown($path),
            'epub' => new EpubReader()->toMarkdown($path),
            'fb2' => new Fb2Reader()->toMarkdown($path),
            default => throw new InvalidArgumentException("No built-in reader for .{$extension} files."),
        };
    }
}
