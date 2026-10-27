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
        return new self(__('This workspace can have :limit agents. Please contact your administrator to add more.', ['limit' => $limit]));
    }
}
