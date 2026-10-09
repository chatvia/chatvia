<?php

declare(strict_types=1);

namespace Domain\Agent\Data\Resources\ChatLog\Countries;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Distinct visitor countries seen in the agent's chat sessions.
 */
#[TypeScript]
class ChatLogCountriesResourceData extends Data
{
    public function __construct(
        /** @var DataCollection<int, ChatLogCountryData> */
        #[DataCollectionOf(ChatLogCountryData::class)]
        public DataCollection $countries,
    ) {}
}
