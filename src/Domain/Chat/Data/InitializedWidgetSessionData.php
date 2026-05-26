<?php

namespace Domain\Chat\Data;

use Spatie\LaravelData\Data;

class InitializedWidgetSessionData extends Data
{
    public function __construct(
        public string $sessionId,
        public string $threadId,
        public bool $isNewSession,
        public bool $isNewThread,
    ) {}
}
