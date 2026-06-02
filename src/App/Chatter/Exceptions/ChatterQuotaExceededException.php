<?php

namespace App\Chatter\Exceptions;

use App\Chatter\Actions\CreateChatterAction;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown by {@see CreateChatterAction} when a workspace tries to provision
 * a chatter beyond its `workspace_users_limit` — the shared cap covering
 * both team members and chatters. Self-renders to 402 Payment Required so
 * API clients can translate it to an upgrade prompt without per-controller
 * try/catch.
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
            "You've reached your workspace user limit ({$limit}), shared between team members and chatters. Upgrade your plan to add more.",
            $limit,
        );
    }
}
