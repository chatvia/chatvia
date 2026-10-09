<?php

namespace App\Mcp\Tools\Charts;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Line chart: one or more series over ordered categories or a time axis.
 */
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
class RenderLineChartTool extends EChartsTool
{
    protected const string TITLE_HINT = 'Optional chart title shown at the top.';

    protected const string SUMMARY_HINT = 'One sentence describing what the chart shows. The LLM sees this on follow-up turns; users see the chart, not this text.';

    protected const array AXIS_LABELS = [
        'xAxisLabel' => 'Optional x-axis label.',
        'yAxisLabel' => 'Optional y-axis label.',
    ];

    protected function chartRules(): array
    {
        return [
            'xAxisType' => ['nullable', 'in:category,time'],
            'xAxis' => ['required', 'array', 'min:1'],
            'xAxis.*' => ['required'],
            'series' => ['required', 'array', 'min:1'],
            'series.*.name' => ['required', 'string'],
            'series.*.data' => ['required', 'array'],
            'series.*.data.*' => ['required', 'numeric'],
            'series.*.smooth' => ['nullable', 'boolean'],
            'series.*.area' => ['nullable', 'boolean'],
        ];
    }

    protected function chartSchema(JsonSchema $schema): array
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
        ];
    }

    protected function option(array $input): array
    {
        $xAxis = $this->withAxisName([
            'type' => $input['xAxisType'] ?? 'category',
            'data' => array_map('strval', $input['xAxis']),
            'boundaryGap' => false,
        ], $input, 'xAxisLabel');

        $series = array_map(static function (array $series): array {
            $item = ['type' => 'line', 'name' => $series['name'], 'data' => $series['data']];
            if (! empty($series['smooth'])) {
                $item['smooth'] = true;
            }
            if (! empty($series['area'])) {
                $item['areaStyle'] = (object) [];
            }

            return $item;
        }, $input['series']);

        return [
            'tooltip' => ['trigger' => 'axis'],
            'legend' => ['show' => count($series) > 1, 'top' => 'bottom'],
            'grid' => ['left' => 50, 'right' => 30, 'top' => $this->hasTitle($input) ? 50 : 20, 'bottom' => count($series) > 1 ? 40 : 20, 'containLabel' => true],
            'xAxis' => $xAxis,
            'yAxis' => $this->withAxisName(['type' => 'value'], $input, 'yAxisLabel'),
            'series' => $series,
        ];
    }
}
