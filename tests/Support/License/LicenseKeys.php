<?php

declare(strict_types=1);

namespace Tests\Support\License;

use Support\License\LicenseFeature;
use Support\License\LicenseKey;
use Support\License\LicenseSignature;

/**
 * License keys for tests, signed with a fixed test key pair (the Ed25519 seed
 * is 32 bytes of 0x07). The chatvia.ai site's tests sign the same terms with
 * the same pair and expect {@see BOUGHT_KEY} exactly, so a change to the key
 * format on either side fails a test.
 */
final class LicenseKeys
{
    public const string PUBLIC_KEY = '6kpsY+KcUgq+9VB7Ey7F+ZVHdq6+vnuSQh7qaRRG0iw=';

    public const string SECRET_KEY = 'BwcHBwcHBwcHBwcHBwcHBwcHBwcHBwcHBwcHBwcHBwfqSmxj4pxSCr71UHsTLsX5lUd2rr6+e5JCHuppFEbSLA==';

    public const string ID = '0199e6a0-0000-7000-8000-000000000001';

    /** Acme Ltd, as bought (no domain), hiding the credit, made on 2026-10-15. */
    public const string BOUGHT_KEY = 'cvl1.eyJpZCI6IjAxOTllNmEwLTAwMDAtNzAwMC04MDAwLTAwMDAwMDAwMDAwMSIsImxpY2Vuc2VlIjoiQWNtZSBMdGQiLCJkb21haW5zIjpbXSwiZmVhdHVyZXMiOlsiaGlkZV9jcmVkaXQiXSwiaXNzdWVkIjoiMjAyNi0xMC0xNSJ9.LTX1qrT91tj3ttijRN9U5cIy8PBGPVO6Sf3k-XE7ynEj51CEvsaVc0OJCMxkvHfDyQoM13u8sbvOd8vmvXjnBg';

    /** Makes the app check keys against the test public key. */
    public static function trust(): void
    {
        config(['chatvia.license.public_key' => self::PUBLIC_KEY]);
    }

    /** Acme Ltd's key activated for $domains (as bought with none). */
    public static function activatedFor(string ...$domains): string
    {
        return LicenseSignature::sign(
            new LicenseKey(self::ID, 'Acme Ltd', array_values($domains), [LicenseFeature::HideCredit], '2026-10-15'),
            self::SECRET_KEY,
        );
    }
}
