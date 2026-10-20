<?php

declare(strict_types=1);

namespace Domain\Knowledge\Document\Conversion;

use Domain\Knowledge\Document\Contracts\PdfInspector;
use Domain\Knowledge\Source\File\Data\FileAnalysisData;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use RuntimeException;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Document;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Reads a PDF's page count and lock state in PHP, for a server without
 * poppler's pdfinfo ({@see PdfInfo}, which is used whenever it is found).
 *
 * The page count comes from smalot/pdfparser. That parser cannot decrypt, so
 * an encrypted PDF's count comes from the root of its page tree instead,
 * which {@see PdfEncryption} reads with the file's key when the page tree
 * sits in an encrypted object stream. A PDF neither can read fails like one
 * pdfinfo cannot read. The lock check is {@see PdfEncryption} too.
 */
final readonly class BuiltinPdfInspector implements PdfInspector
{
    /** Largest stream the parser inflates, a guard against zip bombs. */
    private const int DECODE_LIMIT_BYTES = 64 * 1024 * 1024;

    /** The document's Info entries kept, in pdfinfo's key names. */
    private const array DETAILS = [
        'Title' => 'title',
        'Subject' => 'subject',
        'Keywords' => 'keywords',
        'Author' => 'author',
        'Creator' => 'creator',
        'Producer' => 'producer',
        'CreationDate' => 'creationdate',
        'ModDate' => 'moddate',
    ];

    public function __construct(
        private PdfEncryption $encryption,
    ) {}

    public function getInfo(string $pdfPath): FileAnalysisData
    {
        $this->validatePdfFile($pdfPath);

        $encrypted = $this->encryption->isEncrypted($pdfPath);
        $parsed = $this->parse($pdfPath);

        // The parser cannot decrypt, so an encrypted file's count comes from
        // its page tree; so does a file the parser cannot read at all.
        $pages = $encrypted || $parsed === null ? $this->pageTreeCount($pdfPath) : $parsed['pages'];

        if ($pages === 0 && $parsed !== null) {
            $pages = $parsed['pages'];
        }

        if ($pages === 0) {
            throw new RuntimeException('The PDF reader found no pages.');
        }

        return new FileAnalysisData(
            length: $pages,
            metadata: [
                // An encrypted file's text entries are encrypted too.
                ...($encrypted || $parsed === null ? [] : $this->details($parsed['details'])),
                'pages' => (string) $pages,
                'encrypted' => $encrypted ? 'yes' : 'no',
                'pdf_version' => $this->version($pdfPath),
            ],
        );
    }

    public function isLocked(string $pdfPath): bool
    {
        return $this->encryption->needsPassword($pdfPath);
    }

    /**
     * The page count and Info entries the parser reads, or null when it
     * cannot read the file.
     *
     * @return array{pages: int, details: array<string, mixed>}|null
     */
    private function parse(string $pdfPath): ?array
    {
        $config = new Config;
        $config->setRetainImageContent(false);
        $config->setIgnoreEncryption(true);
        $config->setDecodeMemoryLimit(self::DECODE_LIMIT_BYTES);

        try {
            $document = new Parser([], $config)->parseFile($pdfPath);

            return ['pages' => $this->rootCount($document) ?: count($document->getPages()), 'details' => $document->getDetails()];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The `/Count` of the page tree the catalog points to, as poppler reads
     * it: walking the tree misses pages when a later update rewrote part of
     * it. Zero when the file has none.
     */
    private function rootCount(Document $document): int
    {
        try {
            $count = $document->getTrailer()->get('Root')->get('Pages')->get('Count')->getContent();
        } catch (Throwable) {
            return 0;
        }

        return is_numeric($count) ? max(0, (int) $count) : 0;
    }

    /**
     * The page count the root of the page tree gives: the largest `/Count`
     * of a `/Pages` node. Inner nodes count fewer, and a node an update
     * rewrote (a signature adds one) still counts the same, so older copies
     * do not add up.
     */
    private function pageTreeCount(string $pdfPath): int
    {
        $counts = array_map(
            fn (array $node): int => is_int($node['Count'] ?? null) ? $node['Count'] : 0,
            array_filter($this->encryption->dictionaries($pdfPath), fn (array $dictionary): bool => ($dictionary['Type'] ?? null) === '/Pages'),
        );

        return $counts === [] ? 0 : max(0, ...$counts);
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array<string, string>
     */
    private function details(array $details): array
    {
        $info = [];

        foreach (self::DETAILS as $name => $key) {
            $value = $details[$name] ?? null;

            if (is_scalar($value) && trim((string) $value) !== '') {
                $info[$key] = mb_convert_encoding(trim((string) $value), 'UTF-8', 'UTF-8');
            }
        }

        return $info;
    }

    /** The version in the file's header (`%PDF-1.7`), as pdfinfo reports it. */
    private function version(string $pdfPath): string
    {
        $header = (string) file_get_contents($pdfPath, false, null, 0, 1024);

        return preg_match('/%PDF-(\d+\.\d+)/', $header, $match) === 1 ? $match[1] : '';
    }

    private function validatePdfFile(string $path): void
    {
        if (! File::exists($path)) {
            throw new InvalidArgumentException("PDF file does not exist: {$path}");
        }

        if (! File::isReadable($path)) {
            throw new InvalidArgumentException("PDF file is not readable: {$path}");
        }

        if (File::size($path) === 0) {
            throw new InvalidArgumentException("PDF file is empty: {$path}");
        }

        $mimeType = File::mimeType($path);

        if ($mimeType !== 'application/pdf') {
            throw new InvalidArgumentException("File is not a PDF: {$path} (detected: {$mimeType})");
        }
    }
}
