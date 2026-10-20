<?php

declare(strict_types=1);

namespace App\Console\Commands\License;

use App\Admin\Settings\Actions\CheckLicenseAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Runs the daily license check ({@see CheckLicenseAction}): a key that was
 * deactivated at chatvia.ai is removed, so the "Powered by" credit shows
 * again. Does nothing without a saved key.
 */
#[Description('Check that the saved license key is still active at chatvia.ai; remove it if not.')]
#[Signature('chatvia:license:check')]
class CheckLicense extends Command
{
    public function handle(CheckLicenseAction $checkLicense): int
    {
        if ($checkLicense->execute()) {
            $this->warn('The license key is no longer active at chatvia.ai, so it was removed. The "Powered by" credit shows again.');
        }

        return self::SUCCESS;
    }
}
