<?php

namespace Domain\Analytics\Data\Account\Audience;

/**
 * The most active identified end-users over a window, plus how many distinct
 * identified users wrote and how many new visitors arrived.
 */
final readonly class TopUsersData
{
    /**
     * @param  list<TopUserData>  $topUsers  Most messages first.
     */
    public function __construct(
        public array $topUsers,
        public int $uniqueIdentifiedUsers,
        public int $newVisitors,
    ) {}
}
