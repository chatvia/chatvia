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

#[Description(<<<'TXT'
Render a combination bar + line chart with dual y-axes. Use when two metrics
share an x-axis but have different scales: "revenue (bars) vs. profit margin %
(line)", "page views (bars) vs. conversion rate (line)".

Inputs:
  - categories: shared x-axis labels.
  - barSeries: one or more bar series mapped to the LEFT y-axis.
  - lineSeries: one or more line series mapped to the RIGHT y-axis.
  - leftAxisLabel / rightAxisLabel: optional axis names.

The two y-axes are independent — the left scales to bar data, the right to
line data. Don't try to render combos any other way; this is the one tool
for the job.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
class RenderComboChartTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $v = $request->validate([
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => ['required', 'string'],
            'barSeries' => ['required', 'array', 'min:1'],
            'barSeries.*.name' => ['required', 'string'],
            'barSeries.*.data' => ['required', 'array'],
            'barSeries.*.data.*' => ['required', 'numeric'],
            'lineSeries' => ['required', 'array', 'min:1'],
            'lineSeries.*.name' => ['required', 'string'],
            'lineSeries.*.data' => ['required', 'array'],
            'lineSeries.*.data.*' => ['required', 'numeric'],
            'title' => ['nullable', 'string', 'max:120'],
            'leftAxisLabel' => ['nullable', 'string', 'max:60'],
            'rightAxisLabel' => ['nullable', 'string', 'max:60'],
            'summary' => ['required', 'string', 'max:500'],
        ]);

        $bars = array_map(fn ($s) => [
            'type' => 'bar',
            'name' => $s['name'],
            'data' => $s['data'],
            'yAxisIndex' => 0,
        ], $v['barSeries']);

        $lines = array_map(fn ($s) => [
            'type' => 'line',
            'name' => $s['name'],
            'data' => $s['data'],
            'yAxisIndex' => 1,
            'smooth' => true,
        ], $v['lineSeries']);

        $leftAxis = ['type' => 'value', 'position' => 'left'];
        if (! empty($v['leftAxisLabel'])) {
            $leftAxis['name'] = $v['leftAxisLabel'];
        }
        $rightAxis = ['type' => 'value', 'position' => 'right'];
        if (! empty($v['rightAxisLabel'])) {
            $rightAxis['name'] = $v['rightAxisLabel'];
        }

        $option = [
            'tooltip' => ['trigger' => 'axis'],
            'legend' => ['show' => true, 'top' => 'bottom'],
            'grid' => ['left' => 60, 'right' => 60, 'top' => ! empty($v['title']) ? 50 : 20, 'bottom' => 40, 'containLabel' => true],
            'xAxis' => ['type' => 'category', 'data' => $v['categories']],
            'yAxis' => [$leftAxis, $rightAxis],
            'series' => array_merge($bars, $lines),
        ];

        if (! empty($v['title'])) {
            $option['title'] = ['text' => $v['title'], 'left' => 'center'];
        }

        return Response::make(Response::text($v['summary']))
            ->withStructuredContent(['echarts_option' => $option]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'categories' => $schema->array()
                ->items($schema->string())
                ->description('Shared x-axis labels, e.g. ["Q1","Q2","Q3","Q4"].')
                ->required(),

            'barSeries' => $schema->array()
                ->items($schema->object([
                    'name' => $schema->string()->description('Series name.')->required(),
                    'data' => $schema->array()->items($schema->number())->description('Numeric values aligned with `categories`.')->required(),
                ]))
                ->description('Bar series rendered against the LEFT y-axis. Typical: absolute amounts (revenue, count).')
                ->required(),

            'lineSeries' => $schema->array()
                ->items($schema->object([
                    'name' => $schema->string()->description('Series name.')->required(),
                    'data' => $schema->array()->items($schema->number())->description('Numeric values aligned with `categories`.')->required(),
                ]))
                ->description('Line series rendered against the RIGHT y-axis. Typical: rates, percentages, ratios.')
                ->required(),

            'title' => $schema->string()->description('Optional chart title shown at the top.'),
            'leftAxisLabel' => $schema->string()->description('Optional name for the LEFT y-axis (bar series).'),
            'rightAxisLabel' => $schema->string()->description('Optional name for the RIGHT y-axis (line series).'),

            'summary' => $schema->string()
                ->description('One sentence describing what the chart shows.')
                ->required(),
        ];
    }
}
