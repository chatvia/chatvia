<?php

namespace Domain\Integration\Mcp\Services\Tools\Analytics;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Query\Builder;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description(<<<'TXT'
What the AI relied on for a period: the most-called custom functions (webhook
tools), the most-used MCP connector tools, and the most-retrieved knowledge
sources. Answers "which tools does my agent use most?", "what knowledge gets
pulled the most?".
TXT)]
#[IsReadOnly]
#[IsIdempotent]
class GetAiUsageTool extends AnalyticsTool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $v = $request->validate([
            ...$this->commonRules(),
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $workspace = $this->workspace();
        if (! $workspace) {
            return Response::error('No workspace context for this analytics request.');
        }

        $period = $this->resolvePeriod($workspace, $v);
        $scope = $this->agentScope($workspace, $v);
        $limit = (int) ($v['limit'] ?? 5);

        // Per-day usage rollups — filter on bucket_day::date so a mid-day cycle
        // start keeps its first partial day (same as the topics/satisfaction tools).
        $daily = fn (string $table): Builder => $this->analytics()->table($table)
            ->where('workspace_id', $workspace->id)
            ->whereRaw('bucket_day BETWEEN ?::date AND ?::date', [$period->start->toDateString(), $period->end->toDateString()])
            ->when($scope['agent'], fn ($q) => $q->where('agent_id', $scope['agent']->id));

        $functions = $daily('analytics_agent_function_daily')
            ->groupBy('function_id')
            ->selectRaw('MAX(function_name) name, SUM(invocation_count) calls')
            ->orderByRaw('SUM(invocation_count) DESC')
            ->limit($limit)
            ->get()
            ->map(fn ($r): array => ['name' => (string) $r->name, 'calls' => (int) $r->calls])
            ->all();

        $mcpTools = $daily('analytics_agent_mcp_tool_daily')
            ->groupBy('server_slug', 'tool_name')
            ->selectRaw('tool_name, MAX(server_name) server, SUM(invocation_count) calls')
            ->orderByRaw('SUM(invocation_count) DESC')
            ->limit($limit)
            ->get()
            ->map(fn ($r): array => ['tool' => (string) $r->tool_name, 'server' => (string) $r->server, 'calls' => (int) $r->calls])
            ->all();

        $sources = $daily('analytics_agent_source_daily')
            ->groupBy('sourceable_type', 'sourceable_id')
            ->selectRaw('MAX(source_name) name, SUM(retrieval_count) retrievals')
            ->orderByRaw('SUM(retrieval_count) DESC')
            ->limit($limit)
            ->get()
            ->map(fn ($r): array => ['name' => (string) ($r->name ?: 'Untitled'), 'retrievals' => (int) $r->retrievals])
            ->all();

        $parts = [];
        if ($functions !== []) {
            $parts[] = 'top function "'.$functions[0]['name'].'" ('.number_format($functions[0]['calls']).' calls)';
        }
        if ($mcpTools !== []) {
            $parts[] = 'top MCP tool "'.$mcpTools[0]['tool'].'" ('.number_format($mcpTools[0]['calls']).')';
        }
        if ($sources !== []) {
            $parts[] = 'most-used source "'.$sources[0]['name'].'"';
        }

        $summary = $parts === []
            ? sprintf('No AI usage data %s yet.', $period->label)
            : ucfirst($period->label).': '.implode('; ', $parts).'.';

        return Response::make(Response::text($summary))->withStructuredContent([
            'period' => ['key' => $period->key, 'label' => $period->label, 'start' => $period->start->toDateString(), 'end' => $period->end->toDateString()],
            'top_functions' => $functions,
            'top_mcp_tools' => $mcpTools,
            'top_knowledge_sources' => $sources,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->commonSchema($schema),
            'limit' => $schema->integer()
                ->description('How many to return per category (1–20). Defaults to 5.')
                ->default(5),
        ];
    }
}
