<?php

namespace Domain\Analytics\Data\Account\Audience;

/**
 * Conversations per channel over a window.
 */
final readonly class ChannelBreakdownData
{
    /**
     * @param  list<ChannelShareData>  $channels  Busiest first.
     */
    public function __construct(
        public int $totalConversations,
        public array $channels,
    ) {}
}
