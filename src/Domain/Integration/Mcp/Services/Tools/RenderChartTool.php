<?php

namespace Domain\Integration\Mcp\Services\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Renders an interactive chart inside the chat bubble. The tool returns
 * two things in one MCP response:
 *
 *   1. A text content block (the `summary`) — what the LLM sees on its
 *      next turn so it can reason about the chart in follow-ups
 *      ("which region was lowest?"). The model never reads the option spec.
 *
 *   2. A `structuredContent.echarts_option` payload — the raw ECharts
 *      `option` object. The chat dispatcher promotes it to a UI artifact;
 *      the Vue renderer mounts <EChartBlock> with it.
 *
 * No external API is called — this tool is a pure shape transformer. The
 * heavy lifting (axes, scaling, legends, tooltips) happens in the
 * browser via ECharts.
 */
#[Description(<<<'TXT'
Render an interactive chart inline in the chat. Pass an Apache ECharts `option` object
(any chart type: bar, line, pie, scatter, radar, gauge, heatmap, etc.) plus a one-line
plain-English `summary` of what the chart shows. The chart renders interactively in
the user's chat bubble — tooltips, hover, and zoom work out of the box.

Use this whenever you have tabular or time-series data the user would understand better
visually than as text. Compose the ECharts option yourself: set `xAxis`, `yAxis`, `series`,
`title`, etc. Series `type` examples: 'bar', 'line', 'pie', 'scatter'. Keep specs small
(<50 data points per series for clarity).
TXT)]
#[IsReadOnly]
#[IsIdempotent]
class RenderChartTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'option' => ['required', 'array'],
            'summary' => ['required', 'string', 'max:500'],
        ], [
            'option.required' => 'You must provide an `option` object — a valid Apache ECharts option spec.',
            'summary.required' => 'You must provide a one-line `summary` describing the chart for the chat context.',
        ]);

        // Hand back the LLM-readable summary as text, plus the spec as
        // structured content. The chat dispatcher's structuredContent
        // promoter looks for `echarts_option` and turns it into an
        // artifact the Vue chat bubble renders.
        return Response::make(Response::text($validated['summary']))
            ->withStructuredContent([
                'echarts_option' => $validated['option'],
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'option' => $schema->object()
                ->description(
                    'Apache ECharts option object. Free-form JSON — set keys like '.
                    '`title`, `tooltip`, `legend`, `xAxis`, `yAxis`, `series`. '.
                    'Each series is `{name, type, data}` where `type` is one of '.
                    "'bar', 'line', 'pie', 'scatter', 'radar', 'gauge', 'heatmap', etc."
                )
                ->required(),

            'summary' => $schema->string()
                ->description(
                    'One sentence describing what the chart shows. The LLM sees this '.
                    'on follow-up turns; users see the chart, not this text.'
                )
                ->required(),
        ];
    }
}
