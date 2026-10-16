<?php

namespace Domain\Analytics\Data\Agent\Usage;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Full payload of the dashboard's source-usage analytics page: the knowledge
 * sources, functions and MCP tools the agent used most, with their last use.
 */
#[TypeScript]
class SourceUsageResourceData extends Data
{
    public function __construct(
        /** @var DataCollection<int, KnowledgeUsageRowData> */
        #[DataCollectionOf(KnowledgeUsageRowData::class)]
        public DataCollection $knowledge,
        /** @var DataCollection<int, FunctionUsageRowData> */
        #[DataCollectionOf(FunctionUsageRowData::class)]
        public DataCollection $functions,
        /** @var DataCollection<int, McpToolUsageRowData> */
        #[DataCollectionOf(McpToolUsageRowData::class)]
        public DataCollection $mcp_tools,
    ) {}

    /**
     * @param  list<SourceUsageData>  $knowledge
     * @param  list<FunctionUsageData>  $functions
     * @param  list<McpToolUsageData>  $mcpTools
     */
    public static function fromUsage(array $knowledge, array $functions, array $mcpTools): self
    {
        return new self(
            knowledge: new DataCollection(KnowledgeUsageRowData::class, array_map(KnowledgeUsageRowData::fromUsage(...), $knowledge)),
            functions: new DataCollection(FunctionUsageRowData::class, array_map(FunctionUsageRowData::fromUsage(...), $functions)),
            mcp_tools: new DataCollection(McpToolUsageRowData::class, array_map(McpToolUsageRowData::fromUsage(...), $mcpTools)),
        );
    }
}
