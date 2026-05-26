<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\RenderChartTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;

#[Name('Charts')]
#[Version('1.0.0')]
#[Instructions(<<<'TXT'
Interactive chart rendering powered by Apache ECharts. Exposes a single tool:
- render-chart: produce an ECharts option spec (bar, line, pie, scatter, radar,
  gauge, heatmap, etc.) that renders inline in the chat with tooltips and zoom.

Use this whenever the user would understand data better visually than as a table
or paragraph — populations, sales trends, comparisons, distributions, etc.
TXT)]
class ChartsServer extends Server
{
    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        RenderChartTool::class,
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
