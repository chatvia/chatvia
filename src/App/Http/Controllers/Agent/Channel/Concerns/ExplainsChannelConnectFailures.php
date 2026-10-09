<?php

namespace App\Http\Controllers\Agent\Channel\Concerns;

use Domain\Channel\Enums\ChannelConnectFailure;

/**
 * The field-error text for a channel connection the domain refused
 * ({@see ChannelConnectFailure}) that reads the same on every channel.
 */
trait ExplainsChannelConnectFailures
{
    private static function publicAgentsOnly(string $channelName): string
    {
        return __(':channel is available for public agents only. Make this agent public on the Security page, then connect it.', ['channel' => $channelName]);
    }
}
