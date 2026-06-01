<?php

namespace Domain\Connectors\Data\Account;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Full payload for the Settings → Connectors page: the featured catalog
 * (each entry optionally bound to a provisioned MCP server) followed by the
 * account's bespoke custom MCP servers.
 */
#[TypeScript]
class AccountConnectorsResourceData extends Data
{
    public function __construct(
        /** @var DataCollection<int, AccountConnectorRowData> */
        #[DataCollectionOf(AccountConnectorRowData::class)]
        public DataCollection $featured,
        /** @var DataCollection<int, AccountConnectorRowData> */
        #[DataCollectionOf(AccountConnectorRowData::class)]
        public DataCollection $custom,
    ) {}
}
