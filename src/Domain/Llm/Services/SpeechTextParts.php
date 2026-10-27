<?php

declare(strict_types=1);

namespace Domain\Llm\Services;

use Illuminate\Support\Str;

/**
 * Cuts a text to read aloud into parts a speech service takes in one call:
 * between sentences (or lines), and a sentence longer than a part between
 * words. The parts keep the text's order and words.
 */
final class SpeechTextParts
{
    /** @return list<string> */
    public function split(string $text, int $max): array
    {
        $text = Str::trim($text);

        if (Str::length($text) <= $max) {
            return $text === '' ? [] : [$text];
        }

        $parts = [];
        $current = '';

        foreach ($this->pieces($text, $max) as $piece) {
            $joined = $current === '' ? $piece : "{$current} {$piece}";

            if (Str::length($joined) <= $max) {
                $current = $joined;

                continue;
            }

            $parts[] = $current;
            $current = $piece;
        }

        return array_values(array_filter([...$parts, $current], fn (string $part): bool => $part !== ''));
    }

    /**
     * Sentences (or, for one longer than $max, its words in runs of at most
     * $max), in order.
     *
     * @return list<string>
     */
    private function pieces(string $text, int $max): array
    {
        return Str::of($text)
            ->split('/(?<=[.!?。！？])\s+|\n+/u')
            ->map(fn (string $sentence): string => Str::trim($sentence))
            ->filter(fn (string $sentence): bool => $sentence !== '')
            ->flatMap(fn (string $sentence): array => Str::length($sentence) <= $max
                ? [$sentence]
                : Str::of(Str::wordWrap($sentence, $max, "\n", true))->explode("\n")->all())
            ->values()
            ->all();
    }
}
