<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\File\Services;

use Domain\Knowledge\Source\File\Data\ArchiveContentsData;
use Domain\Knowledge\Source\File\Data\ExtractedArchiveFileData;
use Domain\Knowledge\Source\File\Enums\KnowledgeFileType;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Takes the knowledge files out of an uploaded zip archive, folders and
 * archives inside it included, and writes them to a folder of this server.
 * Only files of a format the knowledge base reads (by extension) are
 * written; everything else is reported as skipped by name.
 *
 * The archive is untrusted. Entry names are reduced to plain relative paths
 * (no `..`, no leading slash) and never used as paths on this server: each
 * file is written under a random name. Sizes are capped per file, per
 * archive and in number, by the size the entry really has rather than the
 * one its header claims, so a zip bomb stops early. Archives nest at most
 * {@see MAX_DEPTH} levels deep.
 */
final class ArchiveExtractor
{
    /** The largest file taken out of an archive: the document parser's limit. */
    public const int MAX_FILE_BYTES = SourceFileTypes::MAX_UPLOAD_KB * 1024;

    /** The most bytes written from one upload, every nested archive included. */
    public const int MAX_TOTAL_BYTES = 1024 * 1024 * 1024;

    /** The most files taken from one upload; the rest are skipped. */
    public const int MAX_FILES = 500;

    /** How deep archives inside archives are opened: the upload and four inside it. */
    public const int MAX_DEPTH = 5;

    /** The most skipped names kept; a folder of junk does not swell the result. */
    private const int MAX_SKIPPED_NAMES = 100;

    private int $bytesWritten = 0;

    /** @var list<ExtractedArchiveFileData> */
    private array $files = [];

    /** @var list<string> */
    private array $skipped = [];

    /**
     * @param  string  $archivePath  the uploaded archive
     * @param  string  $into  an empty folder the files are written to; the caller deletes it
     */
    public function extract(string $archivePath, string $into): ArchiveContentsData
    {
        $this->bytesWritten = 0;
        $this->files = [];
        $this->skipped = [];

        $this->walk($archivePath, $into, '', 1);

        return new ArchiveContentsData(files: $this->files, skipped: $this->skipped);
    }

    /** Whether the knowledge base reads files of the extension: a document, a recording, or an archive. */
    public static function keeps(string $extension): bool
    {
        return $extension === 'zip' || collect(self::knowledgeExtensions())->containsStrict($extension);
    }

    /**
     * The extensions of every knowledge file type, on or off: what the
     * extractor keeps for the upload to judge.
     *
     * @return list<string>
     */
    private static function knowledgeExtensions(): array
    {
        return collect(KnowledgeFileType::cases())
            ->flatMap(fn (KnowledgeFileType $type): array => $type->extensions())
            ->unique()
            ->values()
            ->all();
    }

    private function walk(string $archivePath, string $into, string $prefix, int $depth): void
    {
        $zip = new ZipArchive;

        if ($zip->open($archivePath, ZipArchive::RDONLY) !== true) {
            $this->skip($prefix === '' ? File::basename($archivePath) : Str::rtrim($prefix, '/'));

            return;
        }

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);

                if ($stat === false) {
                    continue;
                }

                $name = $this->cleanName($stat['name']);

                if ($name === null) {
                    continue;
                }

                $this->take($zip, $index, $stat['size'], $into, $prefix.$name, $depth);
            }
        } finally {
            $zip->close();
        }
    }

    private function take(ZipArchive $zip, int $index, int $declaredSize, string $into, string $name, int $depth): void
    {
        $extension = file_extension($name);

        if (! self::keeps($extension)) {
            $this->skip($name);

            return;
        }

        if ($extension === 'zip' && $depth >= self::MAX_DEPTH) {
            $this->skip($name);

            return;
        }

        if (count($this->files) >= self::MAX_FILES || $declaredSize > self::MAX_FILE_BYTES || $this->bytesWritten + $declaredSize > self::MAX_TOTAL_BYTES) {
            $this->skip($name);

            return;
        }

        // Read one byte past the cap: a header may claim a smaller size than
        // the entry inflates to. An encrypted or damaged entry reads as false.
        $bytes = $zip->getFromIndex($index, self::MAX_FILE_BYTES + 1);

        if ($bytes === false || strlen($bytes) > self::MAX_FILE_BYTES || $this->bytesWritten + strlen($bytes) > self::MAX_TOTAL_BYTES) {
            $this->skip($name);

            return;
        }

        $this->bytesWritten += strlen($bytes);
        $random = Str::uuid7();
        $path = "{$into}/{$random}.{$extension}";
        File::put($path, $bytes);
        unset($bytes);

        if ($extension === 'zip') {
            $this->walk($path, $into, "{$name}/", $depth + 1);
            File::delete($path);

            return;
        }

        $this->files[] = new ExtractedArchiveFileData(path: $path, name: $name);
    }

    /**
     * The entry's name as a plain relative path, or null for a folder, a
     * hidden file or the resource forks macOS adds to archives.
     */
    private function cleanName(string $entry): ?string
    {
        if (Str::endsWith($entry, '/')) {
            return null;
        }

        $segments = Str::of($entry)
            ->replace('\\', '/')
            ->explode('/')
            ->map(fn (string $segment): string => Str::trim($segment))
            ->reject(fn (string $segment): bool => collect(['', '.', '..'])->containsStrict($segment));

        if ($segments->isEmpty() || $segments->contains(fn (string $segment): bool => Str::startsWith($segment, '.') || Str::startsWith($segment, '__MACOSX'))) {
            return null;
        }

        return Arr::join($segments->all(), '/');
    }

    private function skip(string $name): void
    {
        if (count($this->skipped) < self::MAX_SKIPPED_NAMES) {
            $this->skipped[] = $name;
        }
    }
}
