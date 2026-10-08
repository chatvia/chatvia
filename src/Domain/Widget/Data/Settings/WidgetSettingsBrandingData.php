<?php

namespace Domain\Widget\Data\Settings;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * "Powered by …" branding block embedded in the widget settings payload. The
 * label and link come from `chatvia.brand.powered_by`; `url` is empty when no
 * link is configured.
 */
#[TypeScript]
class WidgetSettingsBrandingData extends Data
{
    public function __construct(
        public bool $enabled,
        public string $name,
        public string $url,
        public string $logo_url,
    ) {}
}
