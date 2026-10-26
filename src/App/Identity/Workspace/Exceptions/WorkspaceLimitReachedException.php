<?php

namespace App\Identity\Workspace\Exceptions;

use DomainException;

/**
 * The install already holds as many workspaces as it may without a license
 * key (a free-use limit).
 */
class WorkspaceLimitReachedException extends DomainException
{
    public static function make(): self
    {
        return new self('This install already has its workspace. Without a ChatVia.ai license key it holds one; invite people to it, or add a license key.');
    }
}
