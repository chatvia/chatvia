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

    /**
     * Unlocks the Enterprise security features: SIEM delivery, the network
     * allowlist, audit-log export and workspace data retention. A yearly key
     * that chatvia.ai stops reporting active once its subscription ends.
     */
    case Enterprise = 'enterprise';
}
