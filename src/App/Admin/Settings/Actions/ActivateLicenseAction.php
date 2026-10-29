<?php

declare(strict_types=1);

namespace App\Admin\Settings\Actions;

use App\Admin\Settings\BrandSettings;
use App\Admin\Settings\CreditLicense;
use App\Admin\Settings\Data\LicenseActivationData;
use App\Admin\Settings\LicenseActivation;
use App\Admin\Settings\LicenseStatus;

/**
 * Activates a license key for this install's domain at chatvia.ai and saves
 * the activated key, as the License settings page does when a key is saved
 * there. The setup wizard's License step and `chatvia:license:activate`
 * use it. A saved key is only replaced by one that activated.
 */
final readonly class ActivateLicenseAction
{
    public function __construct(
        private BrandSettings $settings,
        private CreditLicense $license,
        private LicenseActivation $activation,
    ) {}

    public function execute(string $key): LicenseActivationData
    {
        if ($this->license->status($key) === LicenseStatus::Invalid) {
            return new LicenseActivationData(message: 'This is not a valid license key. Check that you copied all of it.');
        }

        $activation = $this->activation->activate($key);

        if ($activation->key !== null) {
            $this->settings->license_key = $activation->key;
            $this->settings->save();
        }

        return $activation;
    }
}
