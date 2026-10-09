<?php

namespace Domain\Analytics\Data\Chat;

use Spatie\LaravelData\Data;

/**
 * One earlier turn of the "Ask your data" chat, replayed by the browser:
 * who spoke (`user` or `assistant`) and what was said.
 */
class AnalyticsChatTurnData extends Data
{
    public function __construct(
        public string $role,
        public string $content,
    ) {}
}
