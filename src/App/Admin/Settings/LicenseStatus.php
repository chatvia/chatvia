<?php

declare(strict_types=1);

namespace App\Admin\Settings;

/**
 * What a license key means for this install ({@see CreditLicense}).
 */
enum LicenseStatus: string
{
    /** No key is saved. */
    case Missing = 'missing';

    /** The text is not a key signed for Chatvia, or it does not unlock the credit. */
    case Invalid = 'invalid';

    /** A real key as bought, not activated for a domain yet ({@see LicenseActivation}). */
    case NotActivated = 'not_activated';

    /**
     * A real yearly key whose last day (grace included) is over. The daily
     * check brings it back once it is renewed at chatvia.ai.
     */
    case Expired = 'expired';

    /** A real key, activated for another domain. */
    case OtherDomain = 'other_domain';

    /** A key that hides the credit on this install. */
    case Valid = 'valid';
}
