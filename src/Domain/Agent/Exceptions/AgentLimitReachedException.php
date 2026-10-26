<?php

declare(strict_types=1);

namespace Domain\Agent\Exceptions;

use DomainException;

/**
 * The workspace already holds as many agents as it may without a license key
 * (a free-use limit).
 */
class AgentLimitReachedException extends DomainException
{
    public static function make(int $limit): self
    {
        return new self(__('This workspace has :limit agents, the most it can have without a ChatVia.ai license key.', ['limit' => $limit]));
    }
}
