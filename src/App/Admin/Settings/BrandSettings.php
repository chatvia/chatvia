<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Spatie\LaravelSettings\Attributes\ShouldBeEncrypted;

/**
 * Whether the "Powered by ChatVia.ai" credit shows under hosted chats and
 * widgets, and the license key that allows hiding it ({@see CreditLicense}).
 */
class BrandSettings extends AdminSettings
{
    #[ConfigKey('chatvia.powered_by')]
    public ?bool $powered_by_enabled = null;

    #[ConfigKey('chatvia.license.key'), ShouldBeEncrypted]
    public ?string $license_key = null;

    public static function group(): string
    {
        return 'brand';
    }
}
