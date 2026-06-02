<?php

namespace Domain\Prompt\Data\Resources;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Collection wrapper used by the agent Persona editor's prompt picker.
 */
#[TypeScript]
class AgentPromptPickerListData extends Data
{
    public function __construct(
        /** @var DataCollection<int, AgentPromptPickerData> */
        #[DataCollectionOf(AgentPromptPickerData::class)]
        public DataCollection $data,
    ) {}
}
