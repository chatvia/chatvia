<?php

declare(strict_types=1);

namespace Domain\Llm\Data\Video;

use Domain\Llm\Enums\VideoResolution;
use Domain\Llm\Enums\VideoShape;
use Spatie\LaravelData\Data;

/**
 * What to film: the prompt the agent wrote, the shape, and the length and
 * resolution the admin picked. Services take other lengths, so each one
 * asks for the nearest it makes ({@see secondsAmong()}, {@see secondsBetween()}).
 */
class VideoPromptData extends Data
{
    public function __construct(
        public string $prompt,
        public VideoShape $shape = VideoShape::Landscape,
        public int $seconds = 8,
        public VideoResolution $resolution = VideoResolution::Hd720,
    ) {}

    /**
     * The nearest of the lengths a model makes; the longer one on a tie.
     *
     * @param  non-empty-list<int>  $choices
     */
    public function secondsAmong(array $choices): int
    {
        return collect($choices)
            ->sortBy(fn (int $choice): array => [abs($choice - $this->seconds), -$choice])
            ->first();
    }

    /** The length kept within what a model makes. */
    public function secondsBetween(int $min, int $max): int
    {
        return max($min, min($max, $this->seconds));
    }
}
