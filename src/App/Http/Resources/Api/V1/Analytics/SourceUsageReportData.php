<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Analytics;

use Domain\Analytics\Data\Agent\Usage\FunctionUsageData;
use Domain\Analytics\Data\Agent\Usage\McpToolUsageData;
use Domain\Analytics\Data\Agent\Usage\SourceUsageData;
use Spatie\LaravelData\Data;

/**
 * What the agent used to answer: the knowledge sources it retrieved and the
 * functions and MCP tools it called, with counts and last use. Labels are
 * denormalized at call time, so a deleted source keeps its real name.
 */
class SourceUsageReportData extends Data
{
    public function __construct(
        /**
         * The knowledge sources the agent retrieved, most used first.
         *
         * @var list<KnowledgeUsageData>
         */
        public array $knowledge,
        /**
         * The webhook functions the agent called, most called first.
         *
         * @var list<FunctionCallUsageData>
         */
        public array $functions,
        /**
         * The MCP tools the agent called, most called first.
         *
         * @var list<McpToolCallUsageData>
         */
        public array $mcp_tools,
    ) {}

    /**
     * @param  list<SourceUsageData>  $knowledge
     * @param  list<FunctionUsageData>  $functions
     * @param  list<McpToolUsageData>  $mcpTools
     */
    public static function fromUsage(array $knowledge, array $functions, array $mcpTools): self
    {
        return new self(
            knowledge: array_map(KnowledgeUsageData::fromUsage(...), $knowledge),
            functions: array_map(FunctionCallUsageData::fromUsage(...), $functions),
            mcp_tools: array_map(McpToolCallUsageData::fromUsage(...), $mcpTools),
        );
    }
}
