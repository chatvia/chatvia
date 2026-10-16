<?php

declare(strict_types=1);

namespace Domain\Notification\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One category's switches: whether it reaches the bell at all, and whether a
 * new one also shows a desktop alert or plays a sound. Desktop and sound need
 * the bell, so they count as off while it is off.
 */
#[TypeScript]
class CategoryPreferenceData extends Data
{
    public function __construct(
        public bool $bell,
        public bool $desktop,
        public bool $sound,
    ) {}
}
