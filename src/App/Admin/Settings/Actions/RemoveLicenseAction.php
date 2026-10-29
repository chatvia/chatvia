<?php

declare(strict_types=1);

namespace App\Admin\Settings\Actions;

use App\Admin\Settings\BrandSettings;
use App\Admin\Settings\CreditLicense;
use App\Admin\Settings\LicenseActivation;
use Support\License\LicenseKey;

/**
 * Removes the saved license key, as the License settings page does when its
 * key is removed there: the key is freed at chatvia.ai, so it can move to
 * another install, and the credit switch goes back on. The setup wizard's
 * License step uses it.
 */
final readonly class RemoveLicenseAction
{
    public function __construct(
        private BrandSettings $settings,
        private CreditLicense $license,
        private LicenseActivation $activation,
    ) {}

    public function execute(): void
    {
        $key = $this->settings->license_key;

        if (filled($key) && $this->license->read($key) instanceof LicenseKey) {
            $this->activation->release($key);
        }

        $this->settings->license_key = null;
        $this->settings->powered_by_enabled = null;
        $this->settings->save();
    }
}
