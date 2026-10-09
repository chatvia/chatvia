<?php

declare(strict_types=1);

namespace Domain\Widget\Data\Auth;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Answer to the widget's first call: may this agent run on the calling page?
 */
#[TypeScript]
class WidgetAgentVerifyResultData extends Data
{
    public function __construct(
        public bool $verified,
    ) {}
}
