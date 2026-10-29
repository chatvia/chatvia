<?php

declare(strict_types=1);

namespace Domain\Agent\Data\ModelChoice;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The agent's image or video model choice as Setup → Model shows it: whether
 * the agent has the capability (else the switch is hidden), whether the admin
 * lets agents offer a choice (else the switch is locked), whether it is on,
 * and the models chatters would pick from.
 */
#[TypeScript]
class AgentMediaModelChoiceData extends Data
{
    public function __construct(
        public bool $capability,
        public bool $allowed,
        public bool $enabled,
        /** @var list<OfferedMediaModelData> */
        public array $models = [],
    ) {}
}
