<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Services\Chunking\Splitting;

use Domain\Knowledge\Ingestion\Contracts\ChunkMeasure;
use Domain\Knowledge\Ingestion\Contracts\Separator;
use Illuminate\Support\Str;

/**
 * Splits a text into chunks of at most a size, at the best place it can: the
 * first separator (paragraphs, say) when its pieces fit, the next one
 * (lines, sentences, words) for a piece that does not, and fixed windows of
 * the measure when no separator is left. Neighbouring pieces are packed
 * together up to the size, and each chunk may repeat whole pieces from the
 * end of the one before (the overlap). The size is in the unit of the
 * measure: tokens or characters.
 */
final readonly class RecursiveSplitter
{
    public int $overlap;

    public function __construct(
        public ChunkMeasure $measure,
        public int $size,
        int $overlap = 0,
    ) {
        $this->overlap = max(0, min($overlap, $size - 1));
    }

    /** The same splitter with another size, such as the room left after a heading. */
    public function withSize(int $size): self
    {
        return new self($this->measure, max(1, $size), $this->overlap);
    }

    /**
     * @param  list<Separator>  $separators  from the coarsest to the finest
     * @return list<string> the non-empty chunks, trimmed, in reading order
     */
    public function split(string $text, array $separators): array
    {
        return self::trimmed($this->chunks($text, $separators));
    }

    /**
     * The chunks as they are cut, untrimmed: joined, they hold the whole
     * text (with overlapping parts repeated).
     *
     * @param  list<Separator>  $separators
     * @return list<string>
     */
    public function chunks(string $text, array $separators, ?int $length = null): array
    {
        if ($text === '') {
            return [];
        }

        if (($length ?? $this->measure->length($text)) <= $this->size) {
            return [$text];
        }

        if ($separators === []) {
            return $this->measure->windows($text, $this->size, $this->overlap);
        }

        $separator = array_shift($separators);
        $parts = $separator->split($text);

        if (count($parts) < 2) {
            return $this->chunks($text, $separators, $length);
        }

        $chunks = [];
        $fitting = [];

        foreach ($parts as $part) {
            $partLength = $this->measure->length($part);

            if ($partLength <= $this->size) {
                $fitting[] = [$part, $partLength];

                continue;
            }

            array_push($chunks, ...$this->pack($fitting), ...$this->chunks($part, $separators, $partLength));
            $fitting = [];
        }

        array_push($chunks, ...$this->pack($fitting));

        return $chunks;
    }

    /**
     * Packs measured pieces, in order, into chunks of at most the size. A
     * new chunk starts with whole pieces from the end of the one before, up
     * to the overlap. A piece larger than the size is a chunk on its own.
     *
     * @param  list<array{0: string, 1: int}>  $pieces  each piece and its length
     * @return list<string>
     */
    public function pack(array $pieces): array
    {
        $chunks = [];
        $current = [];
        $total = 0;

        foreach ($pieces as [$piece, $length]) {
            if ($current !== [] && $total + $length > $this->size) {
                $chunks[] = implode('', array_column($current, 0));

                while ($current !== [] && ($total > $this->overlap || $total + $length > $this->size)) {
                    $total -= array_shift($current)[1];
                }
            }

            $current[] = [$piece, $length];
            $total += $length;
        }

        if ($current !== []) {
            $chunks[] = implode('', array_column($current, 0));
        }

        return $chunks;
    }

    /**
     * @param  list<string>  $chunks
     * @return list<string>
     */
    public static function trimmed(array $chunks): array
    {
        return array_values(array_filter(array_map(Str::trim(...), $chunks), static fn (string $chunk): bool => $chunk !== ''));
    }
}
