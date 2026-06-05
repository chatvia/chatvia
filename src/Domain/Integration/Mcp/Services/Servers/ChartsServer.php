<?php

namespace Domain\Integration\Mcp\Services\Servers;

use Domain\Integration\Mcp\Services\Tools\RenderBarChartTool;
use Domain\Integration\Mcp\Services\Tools\RenderCandlestickChartTool;
use Domain\Integration\Mcp\Services\Tools\RenderComboChartTool;
use Domain\Integration\Mcp\Services\Tools\RenderGaugeChartTool;
use Domain\Integration\Mcp\Services\Tools\RenderHeatmapChartTool;
use Domain\Integration\Mcp\Services\Tools\RenderLineChartTool;
use Domain\Integration\Mcp\Services\Tools\RenderPieChartTool;
use Domain\Integration\Mcp\Services\Tools\RenderRadarChartTool;
use Domain\Integration\Mcp\Services\Tools\RenderSankeyChartTool;
use Domain\Integration\Mcp\Services\Tools\RenderScatterChartTool;
use Domain\Integration\Mcp\Services\Tools\RenderSunburstChartTool;
use Domain\Integration\Mcp\Services\Tools\RenderTreemapChartTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;

#[Name('Charts')]
#[Version('2.0.0')]
#[Instructions(<<<'TXT'
Interactive chart rendering for the chat. Each chart type has its own tool with
a typed schema — pick the right one for the data:

- render-bar-chart     — categorical comparisons (stacked, grouped, horizontal)
- render-line-chart    — trends over ordered categories or time
- render-pie-chart     — share-of-whole (pie or donut)
- render-combo-chart   — bar + line with dual y-axes (revenue vs %)
- render-scatter-chart — distributions / correlations (with optional bubble sizing)
- render-radar-chart   — multi-axis comparisons (spider chart)
- render-heatmap-chart — 2D intensity matrix (e.g. month × hour)
- render-gauge-chart   — single KPI on a scale (percent, score, progress)
- render-sankey-chart  — flow diagrams (budgets, journeys, supply chains)
- render-treemap-chart — hierarchical share-of-whole (nested rectangles)
- render-sunburst-chart — hierarchical proportions (concentric rings)
- render-candlestick-chart — OHLC financial time-series (stocks, crypto, forex)
  with built-in zoom/pan slider for long series

Each tool accepts a clean JSON shape (categories, series, etc.) and a one-line
`summary` describing what the chart shows. The renderer translates your input
to Apache ECharts internally — you don't need to know the ECharts option spec.

Use a chart whenever the user would understand data better visually than as a
table or paragraph.
TXT)]
class ChartsServer extends Server
{
    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        RenderBarChartTool::class,
        RenderLineChartTool::class,
        RenderPieChartTool::class,
        RenderComboChartTool::class,
        RenderScatterChartTool::class,
        RenderRadarChartTool::class,
        RenderHeatmapChartTool::class,
        RenderGaugeChartTool::class,
        RenderSankeyChartTool::class,
        RenderTreemapChartTool::class,
        RenderSunburstChartTool::class,
        RenderCandlestickChartTool::class,
    ];

    /**
     * @var array<int, class-string<Server\Resource>>
     */
    protected array $resources = [];

    /**
     * @var array<int, class-string<Prompt>>
     */
    protected array $prompts = [];
}
