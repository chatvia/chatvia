<?php

declare(strict_types=1);

namespace App\Admin\Settings;

/**
 * Whether the "Powered by Chatvia" credit shows under hosted chats and
 * widgets. The product name and links are fixed in `config/chatvia.php`.
 */
class BrandSettings extends AdminSettings
{
    #[ConfigKey('chatvia.brand.powered_by.enabled')]
    public ?bool $powered_by_enabled = null;

    public static function group(): string
    {
        return 'brand';
    }
}
