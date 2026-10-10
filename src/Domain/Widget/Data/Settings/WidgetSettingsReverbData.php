<?php

declare(strict_types=1);

namespace Domain\Widget\Data\Settings;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Public Reverb (WebSocket) settings for live-agent handoff, embedded in the
 * widget settings payload so a widget build needs no Reverb settings of its
 * own. A null host, port or scheme tells the widget to use the server its API
 * is on; a null key turns live chat off.
 */
#[TypeScript]
class WidgetSettingsReverbData extends Data
{
    public function __construct(
        public ?string $key,
        public ?string $host,
        public ?int $port,
        public ?string $scheme,
    ) {}
}
