<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\File\Policies;

use Domain\Knowledge\Source\Concerns\AuthorizesWorkspaceOwnedSource;
use Domain\Knowledge\Source\File\Models\SourceFile;

/**
 * Who may see and change a {@see SourceFile}: its owning workspace
 * (owner and teammates) and platform admins.
 */
class SourceFilePolicy
{
    use AuthorizesWorkspaceOwnedSource;
}
