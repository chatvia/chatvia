<?php

declare(strict_types=1);

namespace App\Admin\Settings;

/**
 * The product name and links an install shows instead of Chatvia's, and the
 * "Powered by" credit under hosted chats and widgets. The name also becomes
 * `app.name`, which emails use; cache and session prefixes keep APP_NAME.
 */
class BrandSettings extends AdminSettings
{
    #[ConfigKey('chatvia.brand.name'), ConfigKey('app.name')]
    public ?string $name = null;

    #[ConfigKey('chatvia.brand.website_url')]
    public ?string $website_url = null;

    #[ConfigKey('chatvia.brand.docs_url')]
    public ?string $docs_url = null;

    #[ConfigKey('chatvia.brand.api_docs_url')]
    public ?string $api_docs_url = null;

    #[ConfigKey('chatvia.brand.powered_by.enabled')]
    public ?bool $powered_by_enabled = null;

    #[ConfigKey('chatvia.brand.powered_by.label')]
    public ?string $powered_by_label = null;

    #[ConfigKey('chatvia.brand.powered_by.url')]
    public ?string $powered_by_url = null;

    public static function group(): string
    {
        return 'brand';
    }
}
