<?php

namespace Domain\Channel\Exceptions;

use Domain\Channel\Enums\ChannelConnectFailure;
use RuntimeException;

/**
 * Thrown by the connect actions when a channel can't be connected to an
 * agent. {@see $failure} says why, so the caller can show the right message.
 */
final class ChannelConnectException extends RuntimeException
{
    private function __construct(public readonly ChannelConnectFailure $failure)
    {
        parent::__construct("Channel connection failed: {$failure->name}.");
    }

    public static function protectedAgent(): self
    {
        return new self(ChannelConnectFailure::ProtectedAgent);
    }

    public static function takenByAnotherAgent(): self
    {
        return new self(ChannelConnectFailure::TakenByAnotherAgent);
    }

    public static function rejected(): self
    {
        return new self(ChannelConnectFailure::Rejected);
    }
}
