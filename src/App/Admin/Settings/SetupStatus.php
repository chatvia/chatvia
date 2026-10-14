<?php

declare(strict_types=1);

namespace App\Admin\Settings;

/**
 * How the setup of a new install ended (`chatvia.admin.setup_status`): done
 * in full, or skipped. Unset while it has not ended: every admin panel page
 * then leads to it. A skipped setup can be finished later from Settings.
 */
enum SetupStatus: string
{
    case Completed = 'completed';
    case Skipped = 'skipped';

    /** The status in use, or null while the setup has not ended. */
    public static function current(): ?self
    {
        return self::tryFrom((string) config('chatvia.admin.setup_status'));
    }
}
