<?php

declare(strict_types=1);

namespace Domain\Knowledge\Document\Conversion\Markdown;

use Domain\Knowledge\Document\Conversion\EbookText;
use Domain\Knowledge\Document\Conversion\OfficeText;
use InvalidArgumentException;

/**
 * The built-in readers that turn documents into GitHub Markdown in PHP, so
 * no program has to be installed on the server: DOCX, ODT, RTF, EPUB and
 * FB2 stand in for pandoc, DOC for catdoc and PPT for catppt. Uploads use
 * them through {@see OfficeText} and {@see EbookText};
 * tests/Eval/DocumentReaderEvalTest.php compares them with the programs.
 */
final class MarkdownReaders
{
    private const array EXTENSIONS = ['docx', 'odt', 'rtf', 'doc', 'ppt', 'epub', 'fb2'];

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
            'rtf' => new RtfReader()->toMarkdown($path),
            'doc' => new DocReader()->toMarkdown($path),
            'ppt' => new PptReader()->toMarkdown($path),
            'epub' => new EpubReader()->toMarkdown($path),
            'fb2' => new Fb2Reader()->toMarkdown($path),
            default => throw new InvalidArgumentException("No built-in reader for .{$extension} files."),
        };
    }

    /**
     * A presentation's text slide by slide.
     *
     * @return list<string>
     */
    public function slides(string $path): array
    {
        return new PptReader()->slides($path);
    }
}
