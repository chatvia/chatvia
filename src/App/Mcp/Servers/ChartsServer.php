<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools\Charts\RenderBarChartTool;
use App\Mcp\Tools\Charts\RenderCandlestickChartTool;
use App\Mcp\Tools\Charts\RenderComboChartTool;
use App\Mcp\Tools\Charts\RenderGaugeChartTool;
use App\Mcp\Tools\Charts\RenderHeatmapChartTool;
use App\Mcp\Tools\Charts\RenderLineChartTool;
use App\Mcp\Tools\Charts\RenderPieChartTool;
use App\Mcp\Tools\Charts\RenderRadarChartTool;
use App\Mcp\Tools\Charts\RenderSankeyChartTool;
use App\Mcp\Tools\Charts\RenderScatterChartTool;
use App\Mcp\Tools\Charts\RenderSunburstChartTool;
use App\Mcp\Tools\Charts\RenderTreemapChartTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;

/**
 * The Charts connector: one typed tool per chart type. The chat UI renders each
 * tool's `echarts_option` result as an interactive chart.
 */
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
    #[\Override]
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
    #[\Override]
    protected array $resources = [];

    /**
     * @var array<int, class-string<Prompt>>
     */
    #[\Override]
    protected array $prompts = [];
}
