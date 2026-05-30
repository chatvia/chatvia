<?php

namespace Domain\Agents\Data\Resources;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Collection wrapper used by the agent setup banner's template picker.
 */
#[TypeScript]
class AgentTemplatePickerListData extends Data
{
    public function __construct(
        /** @var DataCollection<int, AgentTemplatePickerData> */
        #[DataCollectionOf(AgentTemplatePickerData::class)]
        public DataCollection $data,
    ) {}
}
