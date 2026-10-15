<?php

declare(strict_types=1);

namespace App\Admin\Settings\Actions;

use App\Admin\Settings\BrandSettings;
use App\Admin\Settings\CreditLicense;
use App\Admin\Settings\LicenseActivation;

/**
 * The daily license check: asks chatvia.ai whether the saved key is still
 * active for this domain, and removes it when chatvia.ai says no (the buyer
 * deactivated it to move it, or it was refunded). The switch goes back on
 * with it, so the credit shows again and the page says so.
 * No answer keeps the key, so an outage at chatvia.ai changes nothing.
 */
final readonly class CheckLicenseAction
{
    public function __construct(
        private BrandSettings $settings,
        private CreditLicense $license,
        private LicenseActivation $activation,
    ) {}

    /** Whether the key was removed. */
    public function execute(): bool
    {
        $key = $this->settings->license_key;

        if (! $this->license->allowsHidingCredit($key) || $this->activation->isActive((string) $key) !== false) {
            return false;
        }

        $this->settings->license_key = null;
        $this->settings->powered_by_enabled = null;
        $this->settings->save();

        return true;
    }
}
