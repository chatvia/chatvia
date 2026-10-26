<?php

declare(strict_types=1);

namespace Domain\Knowledge\Retrieval\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Knowledge search as an agent's Sources page shows it: whether the install
 * searches at all, whether the agent may turn it off, and whether it searches.
 */
#[TypeScript]
final class KnowledgeSearchStateData extends Data
{
    public function __construct(
        public bool $installEnabled,
        public bool $canChange,
        public bool $enabled,
    ) {}
}
