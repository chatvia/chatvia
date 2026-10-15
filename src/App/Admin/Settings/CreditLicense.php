<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Illuminate\Contracts\Config\Repository as Config;
use Support\License\LicenseFeature;
use Support\License\LicenseKey;
use Support\License\LicenseSignature;

/**
 * Decides whether a license key allows hiding the "Powered by" credit on this
 * install: it must be signed for the public key in `chatvia.license`, unlock
 * {@see LicenseFeature::HideCredit} and be activated for the host of
 * `app.url`. A key as bought names no domain; {@see LicenseActivation} swaps
 * it for one that does. {@see SettingsConfig} keeps the credit on unless this
 * says yes.
 */
final readonly class CreditLicense
{
    public function __construct(private Config $config) {}

    public function status(?string $key): LicenseStatus
    {
        if (blank($key)) {
            return LicenseStatus::Missing;
        }

        $license = $this->read($key);

        return match (true) {
            $license === null || ! $license->allows(LicenseFeature::HideCredit) => LicenseStatus::Invalid,
            $license->domains === [] => LicenseStatus::NotActivated,
            ! $license->coversHost($this->host()) => LicenseStatus::OtherDomain,
            default => LicenseStatus::Valid,
        };
    }

    /** Whether $key hides the credit on this install. */
    public function allowsHidingCredit(?string $key): bool
    {
        return $this->status($key) === LicenseStatus::Valid;
    }

    /** The terms of a key signed for Chatvia, whatever domain it names. */
    public function read(?string $key): ?LicenseKey
    {
        return blank($key) ? null : LicenseSignature::verify((string) $key, $this->config->get('chatvia.license.public_key'));
    }

    /** The host this install runs on, from `app.url`. */
    public function host(): string
    {
        return LicenseKey::host((string) parse_url((string) $this->config->get('app.url'), PHP_URL_HOST));
    }
}
