<?php

declare(strict_types=1);

namespace Domain\Conversation\Data\Voice;

use Domain\Integration\Tool\Enums\ToolKind;
use Spatie\LaravelData\Data;

/**
 * How the call view names a tool the call offers: the connector's or
 * function's label and logo, as the typed chat shows them, and for a
 * connector the tool's own name (`update_order`), so the visitor sees what
 * it did. Sent as JSON as-is, so the names are the wire format.
 */
class VoiceToolLabelData extends Data
{
    public function __construct(
        public ToolKind $kind,
        public string $label,
        public ?string $icon_url,
        public ?string $action,
    ) {}
}
