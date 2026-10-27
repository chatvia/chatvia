<?php

namespace App\Mcp\Tools\Analytics;

use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Data\Account\LabelCountData;
use Domain\Analytics\Data\Account\Usage\McpToolUsageData;
use Domain\Analytics\Queries\Account\Usage\AiUsageQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * get-ai-usage: the functions, MCP tools and knowledge the AI used most.
 */
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
    private const int DEFAULT_LIMIT = 5;

    public function __construct(private readonly AiUsageQuery $usage) {}

    protected function answer(AnalyticsScopeData $scope, array $input): Response|ResponseFactory
    {
        $data = $this->usage->get($scope, (int) ($input['limit'] ?? self::DEFAULT_LIMIT));
        $period = $scope->period;

        $functions = array_map(static fn (LabelCountData $function): array => ['name' => $function->label, 'calls' => $function->count], $data->topFunctions);
        $mcpTools = array_map(static fn (McpToolUsageData $tool): array => ['tool' => $tool->tool, 'server' => $tool->server, 'calls' => $tool->calls], $data->topMcpTools);
        $sources = array_map(static fn (LabelCountData $source): array => ['name' => $source->label, 'retrievals' => $source->count], $data->topKnowledgeSources);

        $parts = [];
        if ($functions !== []) {
            $parts[] = 'top function "'.$functions[0]['name'].'" ('.Number::format($functions[0]['calls']).' calls)';
        }
        if ($mcpTools !== []) {
            $parts[] = 'top MCP tool "'.$mcpTools[0]['tool'].'" ('.Number::format($mcpTools[0]['calls']).')';
        }
        if ($sources !== []) {
            $parts[] = 'most-used source "'.$sources[0]['name'].'"';
        }

        $summary = $parts === []
            ? sprintf('No AI usage data %s yet.', $period->label)
            : Str::ucfirst($period->label).': '.implode('; ', $parts).'.';

        return Response::make(Response::text($summary))->withStructuredContent([
            'period' => $this->periodPayload($period),
            'top_functions' => $functions,
            'top_mcp_tools' => $mcpTools,
            'top_knowledge_sources' => $sources,
        ]);
    }

    protected function rules(): array
    {
        return [
            ...parent::rules(),
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];
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
                ->default(self::DEFAULT_LIMIT),
        ];
    }
}
