<?php

declare(strict_types=1);

namespace Domain\Widget\Data\Conversation;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * How many operator messages a widget read receipt marked as read.
 */
#[TypeScript]
class WidgetMarkReadResultData extends Data
{
    public function __construct(
        public int $marked_count,
    ) {}
}
