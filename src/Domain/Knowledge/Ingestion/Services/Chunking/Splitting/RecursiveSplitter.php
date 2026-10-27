<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Services\Chunking\Splitting;

use Domain\Knowledge\Ingestion\Contracts\ChunkMeasure;
use Domain\Knowledge\Ingestion\Contracts\Separator;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Splits a text into chunks of at most a size, at the best place it can: the
 * first separator (paragraphs, say) when its pieces fit, the next one
 * (lines, sentences, words) for a piece that does not, and fixed windows of
 * the measure when no separator is left. Neighbouring pieces are packed
 * together up to the size, and each chunk may repeat whole pieces from the
 * end of the one before (the overlap). A chunk shorter than the minimum is
 * avoided where the text allows it: a short last chunk shares the pieces of
 * the one before evenly, and short text next to a piece cut on its own goes
 * along with it. Tiny chunks carry too little meaning to embed well. The
 * size is in the unit of the measure: tokens or characters.
 */
final readonly class RecursiveSplitter
{
    public int $overlap;

    public int $minimum;

    public function __construct(
        public ChunkMeasure $measure,
        public int $size,
        int $overlap = 0,
        int $minimum = 0,
    ) {
        $this->overlap = max(0, min($overlap, $size - 1));
        $this->minimum = max(0, min($minimum, intdiv($size, 2)));
    }

    /** The same splitter with another size, such as the room left after a heading. */
    public function withSize(int $size): self
    {
        return new self($this->measure, max(1, $size), $this->overlap, $this->minimum);
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

            // A short chunk before the part is cut along with it, and a short
            // last cut of the part is packed with the pieces that follow.
            $before = $this->pack($fitting);
            $short = $this->endsShort($before) ? array_pop($before) : '';
            $cuts = $short === ''
                ? $this->chunks($part, $separators, $partLength)
                : $this->chunks($short.$part, $separators);
            $tail = count($cuts) > 1 && $this->endsShort($cuts) ? array_pop($cuts) : null;

            array_push($chunks, ...$before, ...$cuts);
            $fitting = $tail === null ? [] : [[$tail, $this->measure->length($tail)]];
        }

        array_push($chunks, ...$this->pack($fitting));

        return $chunks;
    }

    /**
     * Packs measured pieces, in order, into chunks of at most the size. A
     * new chunk starts with whole pieces from the end of the one before, up
     * to the overlap. A piece larger than the size is a chunk on its own. A
     * last chunk under the minimum shares the pieces of the one before.
     *
     * @param  list<array{0: string, 1: int}>  $pieces  each piece and its length
     * @return list<string>
     */
    public function pack(array $pieces): array
    {
        $ranges = [];
        $start = 0;
        $total = 0;

        foreach ($pieces as $index => [, $length]) {
            if ($index > $start && $total + $length > $this->size) {
                $ranges[] = [$start, $index];

                while ($start < $index && ($total > $this->overlap || $total + $length > $this->size)) {
                    $total -= $pieces[$start++][1];
                }
            }

            $total += $length;
        }

        if ($pieces !== []) {
            $ranges[] = [$start, count($pieces)];
        }

        return array_map(
            static fn (array $range): string => Arr::join(array_column(array_slice($pieces, $range[0], $range[1] - $range[0]), 0), ''),
            $this->balanced($pieces, $ranges),
        );
    }

    /**
     * The chunks (as piece ranges) with a last one under the minimum evened
     * out with the one before: the cut between them moves back to where the
     * shorter of the two is longest, each still within the size.
     *
     * @param  list<array{0: string, 1: int}>  $pieces
     * @param  list<array{0: int, 1: int}>  $ranges  each chunk's first piece and the piece after its last
     * @return list<array{0: int, 1: int}>
     */
    private function balanced(array $pieces, array $ranges): array
    {
        $count = count($ranges);

        if ($this->minimum === 0 || $count < 2) {
            return $ranges;
        }

        // Running totals, so any run of pieces is measured in one step.
        $totals = [0];

        foreach ($pieces as $index => [, $length]) {
            $totals[] = $totals[$index] + $length;
        }

        $sum = static fn (int $from, int $to): int => $totals[$to] - $totals[$from];
        [$lastStart, $end] = $ranges[$count - 1];
        $shortest = $sum($lastStart, $end);

        if ($shortest >= $this->minimum) {
            return $ranges;
        }

        [$start, $previousEnd] = $ranges[$count - 2];
        $best = null;

        for ($cut = $previousEnd - 1; $cut > $start; $cut--) {
            $own = $sum($cut, $end);

            if ($own > $this->size) {
                break;
            }

            // The overlap: whole pieces from the end of the first chunk, as pack() repeats them.
            $from = $cut;

            while ($from - 1 > $start && $sum($from - 1, $cut) <= $this->overlap && $sum($from - 1, $end) <= $this->size) {
                $from--;
            }

            $smaller = min($sum($start, $cut), $sum($from, $end));

            if ($smaller > $shortest) {
                $shortest = $smaller;
                $best = [[$start, $cut], [$from, $end]];
            }
        }

        if ($best !== null) {
            array_splice($ranges, $count - 2, 2, $best);
        }

        return $ranges;
    }

    /**
     * Whether the last chunk is shorter than the minimum.
     *
     * @param  list<string>  $chunks
     */
    private function endsShort(array $chunks): bool
    {
        return $this->minimum > 0 && $chunks !== [] && $this->measure->length(array_last($chunks)) < $this->minimum;
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
