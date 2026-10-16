<?php

namespace Domain\Agent\Data\Settings\Copy;

use Spatie\LaravelData\Data;

/**
 * The agent's full, ordered list of conversation starters as the owner saves
 * it.
 */
class UpdateStartersData extends Data
{
    /**
     * @param  list<StarterData>  $starters
     */
    public function __construct(
        public array $starters,
    ) {}
}
