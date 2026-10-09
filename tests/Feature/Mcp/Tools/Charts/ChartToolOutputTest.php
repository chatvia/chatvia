<?php

use App\Mcp\Servers\ChartsServer;
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
use Laravel\Mcp\Server\Testing\TestResponse;

/**
 * The chart tools' `echarts_option` output is a contract with the chat UI's
 * ECharts renderer. These snapshots pin the exact JSON-RPC result each tool
 * returns for representative inputs (with and without a title, single and
 * multi series, and the cross-field validation errors).
 */

/** The JSON-RPC `result` (or `error`) of a tool call, as pretty JSON. */
function chartToolResult(TestResponse $response): string
{
    $body = (fn (): array => $this->response->toArray())->call($response);

    return json_encode($body['result'] ?? $body['error'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

dataset('chart tool calls', [
    'bar vertical, single series' => [RenderBarChartTool::class, [
        'categories' => ['Mon', 'Tue', 'Wed'],
        'series' => [['name' => 'Visitors', 'data' => [10, 20.5, 30]]],
        'summary' => 'Visitors per day.',
    ]],
    'bar horizontal, stacked, titled, labelled' => [RenderBarChartTool::class, [
        'categories' => ['North', 'South'],
        'series' => [
            ['name' => 'Q1', 'data' => [1, 2], 'stack' => 'total'],
            ['name' => 'Q2', 'data' => [3, 4], 'stack' => 'total'],
        ],
        'orientation' => 'horizontal',
        'title' => 'Sales by region',
        'xAxisLabel' => 'Revenue',
        'yAxisLabel' => 'Region',
        'summary' => 'Sales by region, stacked by quarter.',
    ]],
    'bar vertical, labelled' => [RenderBarChartTool::class, [
        'categories' => ['A'],
        'series' => [['name' => 'S', 'data' => [5]]],
        'xAxisLabel' => 'Letter',
        'yAxisLabel' => 'Count',
        'summary' => 'One bar.',
    ]],
    'line, smooth area, time axis' => [RenderLineChartTool::class, [
        'xAxis' => ['2024-01-01', '2024-02-01'],
        'xAxisType' => 'time',
        'series' => [
            ['name' => 'Users', 'data' => [100, 150], 'smooth' => true, 'area' => true],
            ['name' => 'Churn', 'data' => [5, 7]],
        ],
        'title' => 'Monthly users',
        'xAxisLabel' => 'Month',
        'yAxisLabel' => 'Users',
        'summary' => 'Users grew month over month.',
    ]],
    'line, numeric categories' => [RenderLineChartTool::class, [
        'xAxis' => [2023, 2024],
        'series' => [['name' => 'Price', 'data' => [1.5, 2]]],
        'summary' => 'Price by year.',
    ]],
    'pie' => [RenderPieChartTool::class, [
        'data' => [['name' => 'Chrome', 'value' => 60], ['name' => 'Firefox', 'value' => 40]],
        'summary' => 'Browser share.',
    ]],
    'pie donut, titled' => [RenderPieChartTool::class, [
        'data' => [['name' => 'Rent', 'value' => 1200.5]],
        'donut' => true,
        'title' => 'Expenses',
        'summary' => 'Expense breakdown.',
    ]],
    'combo, titled, labelled' => [RenderComboChartTool::class, [
        'categories' => ['Q1', 'Q2'],
        'barSeries' => [['name' => 'Revenue', 'data' => [100, 120]]],
        'lineSeries' => [['name' => 'Margin', 'data' => [0.2, 0.25]]],
        'title' => 'Revenue vs margin',
        'leftAxisLabel' => 'EUR',
        'rightAxisLabel' => '%',
        'summary' => 'Revenue and margin by quarter.',
    ]],
    'combo, untitled' => [RenderComboChartTool::class, [
        'categories' => ['Q1'],
        'barSeries' => [['name' => 'Views', 'data' => [10]]],
        'lineSeries' => [['name' => 'CVR', 'data' => [1]]],
        'summary' => 'Views vs conversion.',
    ]],
    'scatter with bubbles and labels' => [RenderScatterChartTool::class, [
        'series' => [
            ['name' => 'EU', 'points' => [['x' => 1, 'y' => 2, 'size' => 4, 'label' => 'DE'], ['x' => 3, 'y' => 4, 'size' => 16]]],
            ['name' => 'US', 'points' => [['x' => 5, 'y' => 6]]],
        ],
        'title' => 'GDP vs life expectancy',
        'xAxisLabel' => 'GDP',
        'yAxisLabel' => 'Years',
        'summary' => 'Richer countries live longer.',
    ]],
    'scatter, plain' => [RenderScatterChartTool::class, [
        'series' => [['name' => 'Points', 'points' => [['x' => 0.5, 'y' => 1]]]],
        'summary' => 'One point.',
    ]],
    'radar' => [RenderRadarChartTool::class, [
        'indicators' => [['name' => 'Battery', 'max' => 24], ['name' => 'Camera', 'max' => 100], ['name' => 'Speed', 'max' => 10]],
        'series' => [['name' => 'Phone A', 'values' => [20, 90, 8]], ['name' => 'Phone B', 'values' => [18, 95, 9]]],
        'title' => 'Phones',
        'summary' => 'Phone comparison.',
    ]],
    'radar, series length mismatch' => [RenderRadarChartTool::class, [
        'indicators' => [['name' => 'A', 'max' => 1], ['name' => 'B', 'max' => 1], ['name' => 'C', 'max' => 1]],
        'series' => [['name' => 'Short', 'values' => [1, 1, 1, 1]]],
        'summary' => 'Broken.',
    ]],
    'heatmap, auto scale' => [RenderHeatmapChartTool::class, [
        'xCategories' => ['00:00', '01:00'],
        'yCategories' => ['Mon', 'Tue'],
        'data' => [['x' => '00:00', 'y' => 'Mon', 'value' => 3], ['x' => '01:00', 'y' => 'Tue', 'value' => 7.5]],
        'title' => 'Activity',
        'summary' => 'Activity by hour.',
    ]],
    'heatmap, explicit scale' => [RenderHeatmapChartTool::class, [
        'xCategories' => ['a'],
        'yCategories' => ['b'],
        'data' => [['x' => 'a', 'y' => 'b', 'value' => 1]],
        'minValue' => 0,
        'maxValue' => 10,
        'summary' => 'One cell.',
    ]],
    'heatmap, unknown x category' => [RenderHeatmapChartTool::class, [
        'xCategories' => ['a'],
        'yCategories' => ['b'],
        'data' => [['x' => 'zz', 'y' => 'b', 'value' => 1]],
        'summary' => 'Broken.',
    ]],
    'heatmap, unknown y category' => [RenderHeatmapChartTool::class, [
        'xCategories' => ['a'],
        'yCategories' => ['b'],
        'data' => [['x' => 'a', 'y' => 'zz', 'value' => 1]],
        'summary' => 'Broken.',
    ]],
    'gauge, defaults' => [RenderGaugeChartTool::class, [
        'value' => 73,
        'summary' => 'Project 73% done.',
    ]],
    'gauge, custom scale' => [RenderGaugeChartTool::class, [
        'value' => 4.2,
        'min' => 1,
        'max' => 5,
        'unit' => ' / 5',
        'title' => 'SLA',
        'summary' => 'SLA score.',
    ]],
    'sankey, derived nodes' => [RenderSankeyChartTool::class, [
        'links' => [['source' => 'Revenue', 'target' => 'Costs', 'value' => 60], ['source' => 'Revenue', 'target' => 'Profit', 'value' => 40]],
        'title' => 'Budget',
        'summary' => 'Budget flow.',
    ]],
    'sankey, explicit nodes' => [RenderSankeyChartTool::class, [
        'links' => [['source' => 'A', 'target' => 'B', 'value' => 1]],
        'nodes' => [['name' => 'B'], ['name' => 'A'], ['name' => 'Isolated']],
        'summary' => 'Flow.',
    ]],
    'treemap, nested' => [RenderTreemapChartTool::class, [
        'data' => [
            ['name' => 'Tech', 'children' => [['name' => 'Apple', 'value' => 3], ['name' => 'Bad node'], ['value' => 9]]],
            ['name' => 'Energy', 'value' => 2],
        ],
        'title' => 'Market cap',
        'summary' => 'Market cap by sector.',
    ]],
    'sunburst, nested' => [RenderSunburstChartTool::class, [
        'data' => [
            ['name' => 'Europe', 'children' => [['name' => 'Germany', 'value' => 83, 'children' => [['name' => 'Berlin', 'value' => 3.7]]]]],
            ['name' => 7, 'value' => 'not-a-number'],
        ],
        'summary' => 'Population.',
    ]],
    'candlestick, short series' => [RenderCandlestickChartTool::class, [
        'data' => [
            ['date' => '2024-01-01', 'open' => 10, 'high' => 12, 'low' => 9, 'close' => 11],
            ['date' => '2024-01-02', 'open' => 11, 'high' => 11.5, 'low' => 8, 'close' => 9],
        ],
        'title' => 'BTC/USD',
        'yAxisLabel' => 'USD',
        'summary' => 'Two days of prices.',
    ]],
    'candlestick, long series without slider' => [RenderCandlestickChartTool::class, [
        'data' => array_map(fn (int $day): array => [
            'date' => sprintf('2024-03-%02d', ($day % 28) + 1),
            'open' => $day,
            'high' => $day + 2,
            'low' => $day - 1,
            'close' => $day + 1,
        ], range(1, 80)),
        'showSlider' => false,
        'summary' => 'Eighty candles.',
    ]],
    'validation error, missing summary' => [RenderBarChartTool::class, [
        'categories' => ['A'],
        'series' => [['name' => 'S', 'data' => [1]]],
    ]],
    'validation error, title too long' => [RenderPieChartTool::class, [
        'data' => [['name' => 'A', 'value' => 1]],
        'title' => str_repeat('x', 121),
        'summary' => 'Too long a title.',
    ]],
]);

it('returns a stable result for each chart call', function (string $tool, array $arguments): void {
    expect(chartToolResult(ChartsServer::tool($tool, $arguments)))->toMatchSnapshot();
})->with('chart tool calls');
