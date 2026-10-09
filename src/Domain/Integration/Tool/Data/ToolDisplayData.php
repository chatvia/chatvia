<?php

declare(strict_types=1);

namespace Domain\Integration\Tool\Data;

use Domain\Integration\Tool\Enums\ToolKind;

/**
 * How the chat UI should present a tool call: the branded label and icon on
 * the SSE tool events, and whether the call is a first-party builtin whose
 * activity indicator the client hides.
 *
 * `activityLabel` is the connector's own "Preparing your form…" phrase; when
 * it is null the client falls back to "Connecting to {label}…".
 */
final readonly class ToolDisplayData
{
    public function __construct(
        public ToolKind $kind,
        public string $label,
        public ?string $activityLabel = null,
        public bool $isBuiltin = false,
        public ?string $iconUrl = null,
        public ?string $brandColor = null,
    ) {}
}
