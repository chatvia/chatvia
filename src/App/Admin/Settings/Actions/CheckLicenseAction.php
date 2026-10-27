<?php

declare(strict_types=1);

namespace App\Admin\Settings\Actions;

use App\Admin\Settings\BrandSettings;
use App\Admin\Settings\CreditLicense;
use App\Admin\Settings\LicenseActivation;
use App\Admin\Settings\LicenseStatus;

/**
 * The daily license check: asks chatvia.ai whether the saved key is still
 * active for this domain, and removes it when chatvia.ai says no (the buyer
 * deactivated it to move it, or it was refunded). The switch goes back on
 * with it, so the credit shows again and the page says so.
 *
 * While the key is active chatvia.ai sends it back signed afresh; it replaces
 * the saved key, so a renewed yearly key's last day moves on, and an expired
 * key renewed since works again. No answer keeps the key as it is: a yearly
 * key still stops on its signed last day, a key that never ends stays.
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

        if (! collect([LicenseStatus::Valid, LicenseStatus::Expired])->containsStrict($this->license->status($key))) {
            return false;
        }

        $check = $this->activation->check($key ?? '');

        if ($check->active === false) {
            $this->settings->license_key = null;
            $this->settings->powered_by_enabled = null;
            $this->settings->save();

            return true;
        }

        if ($check->key !== null && $check->key !== $key && $this->renews($key, $check->key)) {
            $this->settings->license_key = $check->key;
            $this->settings->save();
        }

        return false;
    }

    /** Whether $fresh is the same license as $saved and works here now. */
    private function renews(?string $saved, string $fresh): bool
    {
        return $this->license->status($fresh) === LicenseStatus::Valid
            && $this->license->read($fresh)?->id === $this->license->read($saved)?->id;
    }
}
