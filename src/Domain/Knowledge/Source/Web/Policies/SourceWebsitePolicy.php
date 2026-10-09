<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\Web\Policies;

use Domain\Knowledge\Source\Concerns\AuthorizesWorkspaceOwnedSource;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;

/**
 * Who may see and change a {@see SourceWebsite}: its owning workspace
 * (owner and teammates) and platform admins.
 */
class SourceWebsitePolicy
{
    use AuthorizesWorkspaceOwnedSource;
}
