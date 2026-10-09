<?php

namespace Domain\Widget\Data\Auth;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Signed permission for a widget visitor to join a conversation's presence
 * channel, in the shape the Pusher protocol expects (`auth` signature plus the
 * JSON-encoded member data).
 */
#[TypeScript]
class WidgetBroadcastAuthData extends Data
{
    public function __construct(
        public string $auth,
        public string $channel_data,
    ) {}
}
