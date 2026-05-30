<?php

namespace Domain\Agents\Data\Resources;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Result of attaching an account-level function to an agent — the function
 * data plus the attachment flag.
 */
#[TypeScript]
class AgentFunctionAttachResultData extends Data
{
    public function __construct(
        public AgentFunctionData $data,
        public bool $is_attached = true,
    ) {}
}
