<?php

namespace Domain\Conversation\Enums;

/**
 * Buckets for an answer's retrieval confidence score (0–1). The chat-log
 * filter uses the bands by value (`high` / `medium` / `low`), and the reply
 * recorder uses the medium floor to decide whether the agent found an answer
 * in its knowledge.
 */
enum ConfidenceBand: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    /** Lowest score that counts as high confidence. */
    public const float HIGH_FLOOR = 0.8;

    /**
     * Lowest score that counts as medium confidence. A knowledge answer at or
     * above it counts as "found"; below it the agent is treated as not having
     * found an answer.
     */
    public const float MEDIUM_FLOOR = 0.65;
}
