<?php

namespace Domain\Widget\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * "Powered by Chatvia" branding block embedded in the widget settings payload.
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
