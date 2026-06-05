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
Render an interactive line chart. Use for trends over time or ordered
categories: "stock price over 5 years", "monthly active users", "temperature
by hour".

Inputs:
  - xAxis: either categorical (months, quarters, country names) or time-based
    (ISO date strings).
  - series: one or more lines. Each is {name, data[]} aligned with xAxis.
    Set `smooth: true` for a curved line, `area: true` to fill underneath.

For bar+line dual-axis charts use `render-combo-chart` instead.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
class RenderLineChartTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $v = $request->validate([
            'xAxisType' => ['nullable', 'in:category,time'],
            'xAxis' => ['required', 'array', 'min:1'],
            'xAxis.*' => ['required'],
            'series' => ['required', 'array', 'min:1'],
            'series.*.name' => ['required', 'string'],
            'series.*.data' => ['required', 'array'],
            'series.*.data.*' => ['required', 'numeric'],
            'series.*.smooth' => ['nullable', 'boolean'],
            'series.*.area' => ['nullable', 'boolean'],
            'title' => ['nullable', 'string', 'max:120'],
            'xAxisLabel' => ['nullable', 'string', 'max:60'],
            'yAxisLabel' => ['nullable', 'string', 'max:60'],
            'summary' => ['required', 'string', 'max:500'],
        ]);

        $xAxisType = $v['xAxisType'] ?? 'category';

        $xAxis = ['type' => $xAxisType, 'data' => array_map('strval', $v['xAxis']), 'boundaryGap' => false];
        if (! empty($v['xAxisLabel'])) {
            $xAxis['name'] = $v['xAxisLabel'];
        }
        $yAxis = ['type' => 'value'];
        if (! empty($v['yAxisLabel'])) {
            $yAxis['name'] = $v['yAxisLabel'];
        }

        $series = array_map(function (array $s): array {
            $item = ['type' => 'line', 'name' => $s['name'], 'data' => $s['data']];
            if (! empty($s['smooth'])) {
                $item['smooth'] = true;
            }
            if (! empty($s['area'])) {
                $item['areaStyle'] = (object) [];
            }

            return $item;
        }, $v['series']);

        $option = [
            'tooltip' => ['trigger' => 'axis'],
            'legend' => ['show' => count($series) > 1, 'top' => 'bottom'],
            'grid' => ['left' => 50, 'right' => 30, 'top' => ! empty($v['title']) ? 50 : 20, 'bottom' => count($series) > 1 ? 40 : 20, 'containLabel' => true],
            'xAxis' => $xAxis,
            'yAxis' => $yAxis,
            'series' => $series,
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
            'xAxis' => $schema->array()
                ->items($schema->string())
                ->description('X-axis labels in display order. For category axis: ["Jan","Feb",...] or country names. For time axis: ISO date strings ["2024-01-01","2024-02-01",...].')
                ->required(),

            'xAxisType' => $schema->string()
                ->enum(['category', 'time'])
                ->description('"category" (default) for evenly-spaced discrete labels. "time" for ISO date strings on a time-scaled axis.')
                ->default('category'),

            'series' => $schema->array()
                ->items($schema->object([
                    'name' => $schema->string()->description('Series name shown in legend and tooltips.')->required(),
                    'data' => $schema->array()->items($schema->number())->description('Numeric values, one per xAxis entry, in the same order.')->required(),
                    'smooth' => $schema->boolean()->description('If true, draw a smooth (Bezier) curve instead of straight segments.'),
                    'area' => $schema->boolean()->description('If true, fill the area under the line (good for cumulative/volume views).'),
                ]))
                ->description('One or more line series. Each data array must match the length of `xAxis`.')
                ->required(),

            'title' => $schema->string()->description('Optional chart title shown at the top.'),
            'xAxisLabel' => $schema->string()->description('Optional x-axis label.'),
            'yAxisLabel' => $schema->string()->description('Optional y-axis label.'),

            'summary' => $schema->string()
                ->description('One sentence describing what the chart shows. The LLM sees this on follow-up turns; users see the chart, not this text.')
                ->required(),
        ];
    }
}
