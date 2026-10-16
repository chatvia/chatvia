<?php

declare(strict_types=1);

namespace Domain\Analytics\Enums;

/**
 * A per-message analysis dimension with its own daily rollup table and a
 * fixed set of values (the buckets the charts show, in display order).
 */
enum MessageDimension: string
{
    case Sentiment = 'sentiment';
    case Tone = 'tone';

    /** The daily rollup table; its value column is named after the dimension. */
    public function rollupTable(): string
    {
        return match ($this) {
            self::Sentiment => 'analytics_agent_sentiment_daily',
            self::Tone => 'analytics_agent_tone_daily',
        };
    }

    /**
     * @return list<string>
     */
    public function buckets(): array
    {
        return match ($this) {
            self::Sentiment => array_column(Sentiment::cases(), 'value'),
            self::Tone => Tone::values(),
        };
    }
}
