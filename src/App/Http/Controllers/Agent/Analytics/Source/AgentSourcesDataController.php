<?php

namespace App\Http\Controllers\Agent\Analytics\Source;

use Domain\Agent\Models\Agent;
use Domain\Analytics\Data\Agent\Usage\FunctionUsageData;
use Domain\Analytics\Data\Agent\Usage\McpToolUsageData;
use Domain\Analytics\Data\Agent\Usage\SourceUsageData;
use Domain\Analytics\Queries\Agent\AgentKnowledgeUsageQuery;
use Domain\Analytics\Queries\Agent\AgentToolUsageQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * The knowledge sources, functions and MCP tools the agent used most, with
 * their last use, for the source-usage analytics page.
 */
class AgentSourcesDataController
{
    public function __invoke(Agent $agent, AgentKnowledgeUsageQuery $knowledge, AgentToolUsageQuery $tools): JsonResponse
    {
        return response()->json([
            'knowledge' => array_map($this->knowledgeSource(...), $knowledge->sources($agent)),
            'functions' => array_map($this->functionUsage(...), $tools->functions($agent)),
            'mcp_tools' => array_map($this->mcpTool(...), $tools->mcpTools($agent)),
        ]);
    }

    /**
     * Every field — including the denormalised name/mime — comes from the
     * durable analytics rollup, so a DELETED source still shows with its real
     * label. The generic fallback only covers legacy rows recorded before the
     * name column existed.
     *
     * @return array<string, mixed>
     */
    private function knowledgeSource(SourceUsageData $source): array
    {
        return [
            'name' => $source->name ?? $this->deletedLabel($source->kind),
            'sub_kind' => $source->kind,
            'mime_type' => $source->mimeType,
            'count' => $source->retrievals,
            'last_used_at' => $this->toIso($source->lastUsedOn),
        ];
    }

    private function deletedLabel(string $subKind): string
    {
        return match ($subKind) {
            'source_file' => __('Deleted file'),
            'source_website' => __('Deleted website'),
            'source_text' => __('Deleted text snippet'),
            'source_youtube_video' => __('Deleted YouTube video'),
            'agent_website_url' => __('Deleted web page'),
            default => __('Deleted source'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function functionUsage(FunctionUsageData $function): array
    {
        return [
            'name' => $function->name,
            'description' => $function->description,
            'count' => $function->calls,
            'last_used_at' => $this->toIso($function->lastUsedOn),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mcpTool(McpToolUsageData $tool): array
    {
        return [
            'tool_name' => $tool->toolName,
            'tool_description' => $tool->toolDescription,
            'server_name' => $tool->serverName,
            'server_slug' => $tool->serverSlug,
            'connector_slug' => $tool->connectorSlug,
            'count' => $tool->calls,
            'last_used_at' => $this->toIso($tool->lastUsedOn),
        ];
    }

    /**
     * The pivot timestamps come back from DB::table without a tz suffix.
     * Stamp them as UTC ISO 8601 so the browser's `new Date(...)` parses
     * them correctly instead of treating the string as local time.
     */
    private function toIso(?string $value): ?string
    {
        return $value === null ? null : Carbon::parse($value, 'UTC')->toIso8601ZuluString();
    }
}
