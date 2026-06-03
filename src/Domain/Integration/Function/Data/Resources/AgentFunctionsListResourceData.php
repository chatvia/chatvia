<?php

namespace Domain\Integration\Function\Data\Resources;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class AgentFunctionsListResourceData extends Data
{
    public function __construct(
        /** @var DataCollection<int, AgentFunctionRowData> */
        #[DataCollectionOf(AgentFunctionRowData::class)]
        public DataCollection $data,
    ) {}
}
