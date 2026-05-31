<?php

namespace Domain\Auth\Exceptions;

use Domain\Auth\Actions\CreateChatterAction;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown by {@see CreateChatterAction} when a workspace
 * tries to provision a chatter beyond its `members_limit`. Self-renders to
 * 402 Payment Required so API clients can translate it to an upgrade prompt
 * without per-controller try/catch.
 */
class ChatterQuotaExceededException extends HttpException
{
    public function __construct(
        string $message,
        public readonly int $limit,
    ) {
        parent::__construct(statusCode: 402, message: $message);
    }

    public static function limitReached(int $limit): self
    {
        return new self(
            "You've reached your chatter limit ({$limit}). Upgrade your plan to provision more chatters.",
            $limit,
        );
    }
}
