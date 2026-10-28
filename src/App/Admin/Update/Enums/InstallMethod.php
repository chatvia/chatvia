<?php

declare(strict_types=1);

namespace App\Admin\Update\Enums;

/**
 * How this install was set up, and so how it upgrades. The Docker image sets
 * CHATVIA_INSTALL=docker and the complete installation writes "complete"
 * to its .env; any other install (a git checkout) has none.
 */
enum InstallMethod: string
{
    case Docker = 'docker';
    case Complete = 'complete';

    /** What the update notice tells the admin to do. */
    public function upgradeHint(): string
    {
        return match ($this) {
            self::Docker => __('To upgrade, run ./manage upgrade in the install folder.'),
            self::Complete => __('To upgrade, run the installer again. Automatic updates (sudo chatvia-auto-update on) install new releases of the same major version.'),
        };
    }
}
