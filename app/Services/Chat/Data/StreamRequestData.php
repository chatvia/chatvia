<?php

namespace App\Services\Chat\Data;

use App\Models\Agent;
use Spatie\LaravelData\Data;

class StreamRequestData extends Data
{
    public function __construct(
        public Agent $agent,
        public StreamRequestMessageData $message,
        public string $conversationId,
        public bool $persist = true,
    ) {}
}
