<?php

declare(strict_types=1);

namespace Domain\Conversation\Data\Media;

use Spatie\LaravelData\Data;

/**
 * The signed permission to listen on a conversation's private media channel,
 * in the shape the WebSocket client expects (`{"auth": "key:signature"}`).
 */
class MediaChannelAuthData extends Data
{
    public function __construct(
        public string $auth,
    ) {}
}
