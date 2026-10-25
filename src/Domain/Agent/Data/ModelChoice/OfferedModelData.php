<?php

declare(strict_types=1);

namespace Domain\Agent\Data\ModelChoice;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One model a chatter may pick in the hosted chat or the widget: its catalog
 * id (sent back as `model_id`), its name for people, its maker's logo, and
 * whether it is the agent's own model (picked until the chatter picks another).
 */
#[TypeScript]
class OfferedModelData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $logo,
        public bool $is_default,
    ) {}
}
