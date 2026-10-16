<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Events;

use App\Identity\Workspace\Models\MessageLimit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A workspace's monthly message count just reached 80% of its limit, or the
 * whole limit. Fired once per crossing per month: the count is bumped and
 * read back in one statement, so only one message lands exactly on each mark.
 */
class MessageLimitThresholdReached
{
    use Dispatchable, SerializesModels;

    /**
     * @param  int  $percent  80, or 100 when the limit is reached
     */
    public function __construct(
        public MessageLimit $limit,
        public int $percent,
    ) {}
}
