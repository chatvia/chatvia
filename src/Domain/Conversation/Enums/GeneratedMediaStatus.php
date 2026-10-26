<?php

declare(strict_types=1);

namespace Domain\Conversation\Enums;

/**
 * Where a generated file stands: waiting for its background job, saved and
 * ready to show, or not made. The chat shows a placeholder while pending.
 */
enum GeneratedMediaStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Failed = 'failed';

    /** Whether the job is done with it, either way. */
    public function isSettled(): bool
    {
        return $this !== self::Pending;
    }
}
