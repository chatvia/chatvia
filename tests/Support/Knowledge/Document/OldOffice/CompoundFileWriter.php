<?php

declare(strict_types=1);

namespace Tests\Support\Knowledge\Document\OldOffice;

use Illuminate\Support\Str;

/**
 * Writes an OLE compound file (MS-CFB, version 3, 512-byte sectors) holding
 * the given streams, for tests of the readers of the old Office formats.
 * Streams under 4096 bytes go into the mini stream, as real writers do, so
 * both ways of storing a stream are covered.
 */
final class CompoundFileWriter
{
    private const int SECTOR = 512;

    private const int MINI_SECTOR = 64;

    private const int MINI_CUTOFF = 4096;

    private const int END_OF_CHAIN = 0xFFFFFFFE;

    private const int FREE = 0xFFFFFFFF;

    private const int FAT_SECTOR = 0xFFFFFFFD;

    /**
     * @param  array<string, string>  $streams  name → bytes, all in the root storage
     */
    public static function write(array $streams): string
    {
        $big = array_filter($streams, static fn (string $bytes): bool => strlen($bytes) >= self::MINI_CUTOFF);
        $small = array_filter($streams, static fn (string $bytes): bool => strlen($bytes) < self::MINI_CUTOFF);

        // The mini stream and its allocation table.
        $miniStream = '';
        $miniFat = [];
        $miniStart = [];

        foreach ($small as $name => $bytes) {
            $count = max(1, (int) ceil(strlen($bytes) / self::MINI_SECTOR));
            $first = count($miniFat);
            $miniStart[$name] = (string) $bytes === '' ? self::END_OF_CHAIN : $first;

            for ($index = 0; $index < $count; $index++) {
                $miniFat[] = $index === $count - 1 ? self::END_OF_CHAIN : $first + $index + 1;
            }

            // str_pad, not Str::padRight: these are bytes, and mb_str_pad counts characters.
            $miniStream .= str_pad($bytes, $count * self::MINI_SECTOR, "\0");
        }

        // Regular sectors, in order: the big streams, the mini stream, the mini table, the directory.
        $sectors = [];
        $fat = [];
        $place = static function (string $bytes) use (&$sectors, &$fat): int {
            $count = max(1, (int) ceil(strlen($bytes) / self::SECTOR));
            $first = count($sectors);

            for ($index = 0; $index < $count; $index++) {
                $sectors[] = str_pad(substr($bytes, $index * self::SECTOR, self::SECTOR), self::SECTOR, "\0");
                $fat[] = $index === $count - 1 ? self::END_OF_CHAIN : $first + $index + 1;
            }

            return $first;
        };

        $bigStart = array_map($place, $big);
        $miniStreamStart = $miniStream === '' ? self::END_OF_CHAIN : $place($miniStream);
        $miniFatStart = $miniFat === [] ? self::END_OF_CHAIN : $place(pack('V*', ...$miniFat));
        $miniFatCount = (int) ceil(count($miniFat) * 4 / self::SECTOR);

        $names = array_keys($streams);
        $directory = self::entry('Root Entry', 5, $names === [] ? self::FREE : 1, self::FREE, $miniStreamStart, strlen($miniStream));

        foreach ($names as $index => $name) {
            $start = $bigStart[$name] ?? $miniStart[$name];
            $directory .= self::entry((string) $name, 2, self::FREE, $index + 1 < count($names) ? $index + 2 : self::FREE, $start, strlen($streams[$name]));
        }

        $directoryStart = $place($directory);

        // The allocation table covers every sector, its own included.
        $fatCount = 1;

        while ((count($sectors) + $fatCount) * 4 > $fatCount * self::SECTOR) {
            $fatCount++;
        }

        $fatStart = count($sectors);
        array_push($fat, ...array_fill(0, $fatCount, self::FAT_SECTOR));
        $fatBytes = str_pad(pack('V*', ...$fat), $fatCount * self::SECTOR, "\xFF");

        for ($index = 0; $index < $fatCount; $index++) {
            $sectors[] = substr($fatBytes, $index * self::SECTOR, self::SECTOR);
        }

        $difat = array_pad(range($fatStart, $fatStart + $fatCount - 1), 109, self::FREE);

        $header = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1".Str::repeat("\0", 16)
            .pack('vvvvv', 0x003E, 3, 0xFFFE, 9, 6).Str::repeat("\0", 6)
            .pack('VVVVVVVVV', 0, $fatCount, $directoryStart, 0, self::MINI_CUTOFF, $miniFatStart, $miniFatCount, self::END_OF_CHAIN, 0)
            .pack('V*', ...$difat);

        return $header.implode('', $sectors);
    }

    private static function entry(string $name, int $type, int $child, int $right, int $start, int $size): string
    {
        $utf16 = mb_convert_encoding($name, 'UTF-16LE', 'UTF-8');

        return str_pad($utf16, 64, "\0")
            .pack('vCC', strlen($utf16) + 2, $type, 1)
            .pack('VVV', self::FREE, $right, $child)
            .Str::repeat("\0", 16 + 4 + 16)
            .pack('VVV', $start, $size, 0);
    }
}
