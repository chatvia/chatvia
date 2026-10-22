<?php

declare(strict_types=1);

namespace Domain\Knowledge\Document\Conversion\Markdown;

use RuntimeException;

/**
 * Read-only access to the streams of an OLE compound file, the container of
 * the old binary Office formats (Word `.doc`, PowerPoint `.ppt`). It is a
 * small file system inside the file: sectors chained by an allocation
 * table, a directory of named streams, and a mini stream for the small ones
 * (MS-CFB).
 *
 * An uploaded file is untrusted, so every chain is checked for loops and
 * every stream for its size before it is read, and only the streams asked
 * for are read: a presentation's pictures stay on disk.
 */
final class CompoundFile
{
    private const string SIGNATURE = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";

    /** Largest single stream read. */
    private const int MAX_STREAM_BYTES = 100 * 1024 * 1024;

    private const int END_OF_CHAIN = 0xFFFFFFFE;

    private const int LAST_REGULAR_SECTOR = 0xFFFFFFFA;

    private const int NO_ENTRY = 0xFFFFFFFF;

    private const int STREAM = 2;

    private const int ROOT = 5;

    private int $sectorSize;

    private int $miniSectorSize;

    private int $miniCutoff;

    /** @var list<int> */
    private array $fat = [];

    /** @var list<int> */
    private array $miniFat = [];

    /** @var array<string, array{start: int, size: int}> upper-case stream name → where it is */
    private array $streams = [];

    private string $miniStream = '';

    /**
     * @param  resource  $handle
     */
    private function __construct(private $handle, private readonly int $fileSize) {}

    public static function open(string $path): self
    {
        $handle = @fopen($path, 'rb');
        $size = @filesize($path);

        if ($handle === false || $size === false) {
            throw new RuntimeException('Could not open the document.');
        }

        $file = new self($handle, $size);

        try {
            $file->readHeader();
        } catch (RuntimeException $e) {
            $file->close();

            throw $e;
        }

        return $file;
    }

    /** Whether the file starts with the compound-file signature. */
    public static function isCompoundFile(string $path): bool
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $signature = fread($handle, 8);
        fclose($handle);

        return $signature === self::SIGNATURE;
    }

    /** Whether the root storage has a stream of this name (case is ignored, as in the format). */
    public function has(string $name): bool
    {
        return isset($this->streams[strtoupper($name)]);
    }

    /** The stream's bytes, or null when the file has no such stream. */
    public function read(string $name): ?string
    {
        $entry = $this->streams[strtoupper($name)] ?? null;

        if ($entry === null) {
            return null;
        }

        if ($entry['size'] > self::MAX_STREAM_BYTES) {
            throw new RuntimeException("The document stream {$name} is too large to read.");
        }

        if ($entry['size'] === 0) {
            return '';
        }

        return $entry['size'] < $this->miniCutoff
            ? $this->readMini($entry['start'], $entry['size'])
            : substr($this->readChain($entry['start'], $this->fat, $entry['size']), 0, $entry['size']);
    }

    public function close(): void
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
    }

    private function readHeader(): void
    {
        $header = $this->bytesAt(0, 512);

        if (strlen($header) < 512 || ! str_starts_with($header, self::SIGNATURE)) {
            throw new RuntimeException('The document is not an OLE compound file.');
        }

        $sectorShift = $this->uint16($header, 0x1E);
        $miniShift = $this->uint16($header, 0x20);

        if (! in_array($sectorShift, [9, 12], true) || $miniShift !== 6) {
            throw new RuntimeException('The compound file has an unknown sector size.');
        }

        $this->sectorSize = 1 << $sectorShift;
        $this->miniSectorSize = 1 << $miniShift;
        $this->miniCutoff = $this->uint32($header, 0x38) ?: 4096;

        $this->fat = $this->readFat($header);
        $this->readDirectory($this->uint32($header, 0x30));

        $firstMiniFat = $this->uint32($header, 0x3C);
        $this->miniFat = $firstMiniFat <= self::LAST_REGULAR_SECTOR ? $this->unpackSectors($this->readChain($firstMiniFat, $this->fat)) : [];
    }

    /**
     * The sector allocation table, from the sector list in the header and the
     * chain of further list sectors (the DIFAT).
     *
     * @return list<int>
     */
    private function readFat(string $header): array
    {
        $count = $this->uint32($header, 0x2C);
        $maxSectors = intdiv($this->fileSize, $this->sectorSize);

        if ($count > $maxSectors) {
            throw new RuntimeException('The compound file is damaged.');
        }

        $sectors = array_slice($this->unpackSectors(substr($header, 0x4C, 436)), 0, min($count, 109));
        $next = $this->uint32($header, 0x44);
        $seen = [];
        $perSector = intdiv($this->sectorSize, 4) - 1;

        while (count($sectors) < $count && $next <= self::LAST_REGULAR_SECTOR && ! isset($seen[$next])) {
            $seen[$next] = true;
            $list = $this->unpackSectors($this->sector($next));
            $next = $list[$perSector] ?? self::END_OF_CHAIN;
            array_push($sectors, ...array_slice($list, 0, $perSector));
        }

        $fat = '';

        foreach (array_slice($sectors, 0, $count) as $sector) {
            $fat .= $this->sector($sector);
        }

        return $this->unpackSectors($fat);
    }

    /** Finds the streams in the root storage; the root entry also locates the mini stream. */
    private function readDirectory(int $firstSector): void
    {
        $directory = $this->readChain($firstSector, $this->fat);
        $count = intdiv(strlen($directory), 128);

        if ($count === 0 || ord($directory[0x42]) !== self::ROOT) {
            throw new RuntimeException('The compound file has no root directory.');
        }

        $entry = fn (int $index): string => substr($directory, $index * 128, 128);
        $root = $entry(0);
        $rootStart = $this->uint32($root, 0x74);
        $rootSize = $this->uint32($root, 0x78);

        if ($rootStart <= self::LAST_REGULAR_SECTOR && $rootSize > 0) {
            if ($rootSize > self::MAX_STREAM_BYTES) {
                throw new RuntimeException('The compound file is too large to read.');
            }

            $this->miniStream = $this->readChain($rootStart, $this->fat, $rootSize);
        }

        // The children of a storage form a tree linked by left and right siblings.
        $pending = [$this->uint32($root, 0x4C)];
        $seen = [];

        while ($pending !== []) {
            $index = array_pop($pending);

            if ($index === self::NO_ENTRY || $index >= $count || isset($seen[$index])) {
                continue;
            }

            $seen[$index] = true;
            $child = $entry($index);
            $pending[] = $this->uint32($child, 0x44);
            $pending[] = $this->uint32($child, 0x48);

            if (ord($child[0x42]) !== self::STREAM) {
                continue;
            }

            $nameLength = min(64, max(0, $this->uint16($child, 0x40) - 2));
            $name = mb_convert_encoding(substr($child, 0, $nameLength), 'UTF-8', 'UTF-16LE');

            $this->streams[strtoupper($name)] = [
                'start' => $this->uint32($child, 0x74),
                // Version 3 files may leave garbage in the high half of the size.
                'size' => $this->uint32($child, 0x78),
            ];
        }
    }

    /**
     * The bytes of a sector chain, read in runs of neighbouring sectors.
     *
     * @param  list<int>  $table
     */
    private function readChain(int $start, array $table, ?int $limit = null): string
    {
        $runs = [];
        $sector = $start;
        $seen = [];

        while ($sector <= self::LAST_REGULAR_SECTOR && ($limit === null || count($seen) * $this->sectorSize < $limit)) {
            if (isset($seen[$sector])) {
                throw new RuntimeException('The compound file has a broken sector chain.');
            }

            $seen[$sector] = true;
            $last = array_key_last($runs);

            if ($last !== null && $sector === $runs[$last][0] + $runs[$last][1]) {
                $runs[$last][1]++;
            } else {
                $runs[] = [$sector, 1];
            }

            $sector = $table[$sector] ?? self::END_OF_CHAIN;
        }

        $data = '';

        foreach ($runs as [$first, $length]) {
            $data .= $this->bytesAt(($first + 1) * $this->sectorSize, $length * $this->sectorSize);
        }

        return $data;
    }

    private function readMini(int $start, int $size): string
    {
        $data = '';
        $sector = $start;
        $seen = [];

        while ($sector <= self::LAST_REGULAR_SECTOR && strlen($data) < $size) {
            if (isset($seen[$sector])) {
                throw new RuntimeException('The compound file has a broken sector chain.');
            }

            $seen[$sector] = true;
            $data .= substr($this->miniStream, $sector * $this->miniSectorSize, $this->miniSectorSize);
            $sector = $this->miniFat[$sector] ?? self::END_OF_CHAIN;
        }

        return substr($data, 0, $size);
    }

    private function sector(int $sector): string
    {
        return $this->bytesAt(($sector + 1) * $this->sectorSize, $this->sectorSize);
    }

    private function bytesAt(int $offset, int $length): string
    {
        if ($length < 1 || $offset >= $this->fileSize || fseek($this->handle, $offset) !== 0) {
            return '';
        }

        return (string) fread($this->handle, $length);
    }

    /**
     * @return list<int>
     */
    private function unpackSectors(string $bytes): array
    {
        $bytes = substr($bytes, 0, strlen($bytes) - strlen($bytes) % 4);

        return $bytes === '' ? [] : array_values((array) unpack('V*', $bytes));
    }

    private function uint16(string $bytes, int $offset): int
    {
        return strlen($bytes) >= $offset + 2 ? (int) unpack('v', $bytes, $offset)[1] : 0;
    }

    private function uint32(string $bytes, int $offset): int
    {
        return strlen($bytes) >= $offset + 4 ? (int) unpack('V', $bytes, $offset)[1] : 0;
    }
}
