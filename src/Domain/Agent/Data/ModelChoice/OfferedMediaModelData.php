<?php

declare(strict_types=1);

namespace Domain\Agent\Data\ModelChoice;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One image or video model a chatter may pick in the hosted chat: the
 * service's model id (sent back as `image_model` / `video_model`), its name
 * for people, and whether it is the admin's model (picked until the chatter
 * picks another).
 */
#[TypeScript]
class OfferedMediaModelData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public bool $is_default,
    ) {}
}
