<?php

namespace Domain\Analytics\Enums;

enum SentimentEnum: string
{
    case POSITIVE = 'positive';

    case NEGATIVE = 'negative';

    case NEUTRAL = 'neutral';
}
