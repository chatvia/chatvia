<?php

namespace Domain\Agent\Data\Settings\Copy;

use Spatie\LaravelData\Data;

/**
 * The agent's full list of widget chat-bubble prompts as the owner saves it.
 */
class UpdateBubblesData extends Data
{
    /**
     * @param  list<BubbleData>  $bubbles
     */
    public function __construct(
        public array $bubbles,
    ) {}
}
