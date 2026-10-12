<?php

declare(strict_types=1);

namespace Domain\Analytics\Data\Platform;

use Domain\Analytics\Services\Period;

/**
 * What a platform-analytics question (the admin panel's dashboard) asks
 * about: a window, and optionally one workspace. Null means every workspace
 * on the install.
 */
final readonly class PlatformScopeData
{
    public function __construct(
        public Period $period,
        /** The workspace to narrow to. */
        public ?int $workspaceId = null,
    ) {}

    public function isWholePlatform(): bool
    {
        return $this->workspaceId === null;
    }
}
