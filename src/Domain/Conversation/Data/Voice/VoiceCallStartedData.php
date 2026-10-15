<?php

declare(strict_types=1);

namespace Domain\Conversation\Data\Voice;

use Spatie\LaravelData\Data;

/**
 * What the browser needs once a voice call is open: the WebRTC answer to
 * apply, the call's id (to hang up with), the conversation it is in, how
 * long the call may last, how long to wait for the server to join it, and
 * how to name the tools the agent calls during it. Sent as JSON as-is, so
 * the names are the wire format.
 */
class VoiceCallStartedData extends Data
{
    public function __construct(
        public string $sdp,
        public string $call_id,
        public string $conversation_id,
        public int $max_seconds,
        public int $join_timeout,
        /** @var array<string, VoiceToolLabelData> the call's tools by name; one missing is not shown */
        public array $tools,
        /** The knowledge search's name: the call view shows a search that has a query. */
        public string $knowledge_tool,
    ) {}
}
