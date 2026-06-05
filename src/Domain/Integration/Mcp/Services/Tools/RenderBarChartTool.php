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
Render an interactive bar chart in the chat. Use for categorical comparisons:
"sales by region", "top 10 countries by population", "monthly visitors", etc.

Inputs:
  - categories: the labels on the category axis (e.g. ["Mon","Tue","Wed"])
  - series: one or more bars per category. Each series is {name, data[]} where
    data is aligned with categories. Add `stack` to a string key to stack
    series sharing that key.
  - orientation: "vertical" (default — bars rise from x-axis) or "horizontal"
    (bars extend from y-axis, good for long category labels).

For bar+line dual-axis charts use `render-combo-chart` instead.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
class RenderBarChartTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $v = $request->validate([
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => ['required', 'string'],
            'series' => ['required', 'array', 'min:1'],
            'series.*.name' => ['required', 'string'],
            'series.*.data' => ['required', 'array'],
            'series.*.data.*' => ['required', 'numeric'],
            'series.*.stack' => ['nullable', 'string'],
            'orientation' => ['nullable', 'in:vertical,horizontal'],
            'title' => ['nullable', 'string', 'max:120'],
            'xAxisLabel' => ['nullable', 'string', 'max:60'],
            'yAxisLabel' => ['nullable', 'string', 'max:60'],
            'summary' => ['required', 'string', 'max:500'],
        ]);

        $orientation = $v['orientation'] ?? 'vertical';

        $categoryAxis = ['type' => 'category', 'data' => $v['categories']];
        $valueAxis = ['type' => 'value'];

        if (! empty($v['xAxisLabel'])) {
            if ($orientation === 'vertical') {
                $categoryAxis['name'] = $v['xAxisLabel'];
            } else {
                $valueAxis['name'] = $v['xAxisLabel'];
            }
        }
        if (! empty($v['yAxisLabel'])) {
            if ($orientation === 'vertical') {
                $valueAxis['name'] = $v['yAxisLabel'];
            } else {
                $categoryAxis['name'] = $v['yAxisLabel'];
            }
        }

        $series = array_map(function (array $s): array {
            $item = ['type' => 'bar', 'name' => $s['name'], 'data' => $s['data']];
            if (! empty($s['stack'])) {
                $item['stack'] = $s['stack'];
            }

            return $item;
        }, $v['series']);

        $option = [
            'tooltip' => ['trigger' => 'axis', 'axisPointer' => ['type' => 'shadow']],
            'legend' => ['show' => count($series) > 1, 'top' => 'bottom'],
            'grid' => ['left' => 50, 'right' => 30, 'top' => ! empty($v['title']) ? 50 : 20, 'bottom' => count($series) > 1 ? 40 : 20, 'containLabel' => true],
            'xAxis' => $orientation === 'vertical' ? $categoryAxis : $valueAxis,
            'yAxis' => $orientation === 'vertical' ? $valueAxis : $categoryAxis,
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
            'categories' => $schema->array()
                ->items($schema->string())
                ->description('Category labels on the category axis, e.g. ["Mon","Tue","Wed"] or ["USA","China","India"].')
                ->required(),

            'series' => $schema->array()
                ->items($schema->object([
                    'name' => $schema->string()->description('Series name shown in legend and tooltips.')->required(),
                    'data' => $schema->array()->items($schema->number())->description('Numeric values, one per category, in the same order as `categories`.')->required(),
                    'stack' => $schema->string()->description('Optional stack key. Series sharing the same `stack` value are stacked.'),
                ]))
                ->description('One or more bar series. For a single grouped chart use multiple series without `stack`. For a stacked chart give each series the same `stack` key.')
                ->required(),

            'orientation' => $schema->string()
                ->enum(['vertical', 'horizontal'])
                ->description('Bar orientation. "vertical" (default) for standard column charts. "horizontal" works better when category labels are long.')
                ->default('vertical'),

            'title' => $schema->string()->description('Optional chart title shown at the top.'),
            'xAxisLabel' => $schema->string()->description('Optional x-axis label.'),
            'yAxisLabel' => $schema->string()->description('Optional y-axis label.'),

            'summary' => $schema->string()
                ->description('One sentence describing what the chart shows. The LLM sees this on follow-up turns; users see the chart, not this text.')
                ->required(),
        ];
    }
}
