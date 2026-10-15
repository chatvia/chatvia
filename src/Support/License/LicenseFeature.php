<?php

declare(strict_types=1);

namespace Support\License;

/**
 * What a license key unlocks. The value is stored inside signed keys, so a
 * case's value never changes.
 */
enum LicenseFeature: string
{
    /** Hides the "Powered by …" credit under hosted chats and widgets. */
    case HideCredit = 'hide_credit';
}
