<?php

namespace Domain\Knowledge\Source\Text\Policies;

use Domain\Knowledge\Source\Concerns\AuthorizesWorkspaceOwnedSource;
use Domain\Knowledge\Source\Text\Models\SourceText;

/**
 * Who may see and change a {@see SourceText}: its owning workspace
 * (owner and teammates) and platform admins.
 */
class SourceTextPolicy
{
    use AuthorizesWorkspaceOwnedSource;
}
