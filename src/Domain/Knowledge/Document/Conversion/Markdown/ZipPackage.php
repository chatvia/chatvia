<?php

declare(strict_types=1);

namespace Domain\Knowledge\Document\Conversion\Markdown;

use DOMDocument;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Read-only access to the parts of a zip-based document (DOCX, ODT, EPUB).
 * An uploaded file is untrusted, so every part is size-checked before it is
 * read (a guard against zip bombs) and XML is parsed without network access.
 */
final class ZipPackage
{
    /** Largest single part read. */
    private const int MAX_PART_BYTES = 50 * 1024 * 1024;

    /** Largest total read from one package. */
    private const int MAX_TOTAL_BYTES = 200 * 1024 * 1024;

    private int $bytesRead = 0;

    private function __construct(private readonly ZipArchive $zip) {}

    public static function open(string $path): self
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('Could not open the document as a zip package.');
        }

        return new self($zip);
    }

    /** The part's bytes, or null when the package has no such part. */
    public function read(string $name): ?string
    {
        $name = Str::ltrim(Str::replace('\\', '/', $name), '/');
        $index = $this->zip->locateName($name);

        if ($index === false) {
            // Some packages name a part in another case than they link it.
            $index = $this->zip->locateName($name, ZipArchive::FL_NOCASE);
        }

        if ($index === false) {
            return null;
        }

        $stat = $this->zip->statIndex($index);
        $size = $stat === false ? PHP_INT_MAX : $stat['size'];

        if ($size > self::MAX_PART_BYTES || $this->bytesRead + $size > self::MAX_TOTAL_BYTES) {
            throw new RuntimeException("The document part {$name} is too large to read.");
        }

        $this->bytesRead += $size;
        $bytes = $this->zip->getFromIndex($index);

        return $bytes === false ? null : $bytes;
    }

    /** The part parsed as XML, or null when it is missing or not well-formed. */
    public function xml(string $name): ?DOMDocument
    {
        $bytes = $this->read($name);

        return $bytes === null ? null : self::parseXml($bytes);
    }

    /** Parses XML with no network access; null when it is not well-formed. */
    public static function parseXml(string $xml): ?DOMDocument
    {
        if (blank($xml)) {
            return null;
        }

        $dom = new DOMDocument;

        return @$dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT) ? $dom : null;
    }

    public function close(): void
    {
        $this->zip->close();
    }

    /** Resolves a link target against the folder of the part that holds it. */
    public static function resolve(string $base, string $target): string
    {
        $target = rawurldecode(Str::before($target, '#'));

        $path = Str::startsWith($target, '/') ? $target : (dirname($base) === '.' ? '' : dirname($base).'/').$target;
        $parts = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '..') {
                array_pop($parts);
            } elseif ($segment !== '' && $segment !== '.') {
                $parts[] = $segment;
            }
        }

        return implode('/', $parts);
    }
}
