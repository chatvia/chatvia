<?php

declare(strict_types=1);

namespace Domain\Llm\Data\Realtime;

use Spatie\LaravelData\Data;

/**
 * A voice call the provider opened: the WebRTC answer the browser applies,
 * and the provider's id of the call, which a server worker joins with.
 */
class RealtimeCallData extends Data
{
    public function __construct(
        public string $answerSdp,
        public string $callId,
    ) {}
}
