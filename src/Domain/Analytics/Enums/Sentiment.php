<?php

declare(strict_types=1);

namespace Domain\Analytics\Enums;

/**
 * The visitor's sentiment, as the analysis model classifies it. Stored on
 * messages and conversations and in the daily sentiment rollup; the values
 * must not change.
 */
enum Sentiment: string
{
    case Positive = 'positive';

    case Negative = 'negative';

    case Neutral = 'neutral';
}
