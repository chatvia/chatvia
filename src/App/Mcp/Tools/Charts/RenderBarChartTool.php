<?php

namespace App\Mcp\Tools\Charts;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Bar chart: grouped or stacked bars over categories, vertical or horizontal.
 */
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
class RenderBarChartTool extends EChartsTool
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
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => ['required', 'string'],
            'series' => ['required', 'array', 'min:1'],
            'series.*.name' => ['required', 'string'],
            'series.*.data' => ['required', 'array'],
            'series.*.data.*' => ['required', 'numeric'],
            'series.*.stack' => ['nullable', 'string'],
            'orientation' => ['nullable', 'in:vertical,horizontal'],
        ];
    }

    protected function chartSchema(JsonSchema $schema): array
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
        ];
    }

    protected function option(array $input): array
    {
        $vertical = ($input['orientation'] ?? 'vertical') === 'vertical';

        // The x/y labels follow the screen axes, so they swap between the
        // category and value axis when the bars lie horizontally.
        $categoryAxis = $this->withAxisName(['type' => 'category', 'data' => $input['categories']], $input, $vertical ? 'xAxisLabel' : 'yAxisLabel');
        $valueAxis = $this->withAxisName(['type' => 'value'], $input, $vertical ? 'yAxisLabel' : 'xAxisLabel');

        $series = array_map(static function (array $series): array {
            $item = ['type' => 'bar', 'name' => $series['name'], 'data' => $series['data']];
            if (! empty($series['stack'])) {
                $item['stack'] = $series['stack'];
            }

            return $item;
        }, $input['series']);

        return [
            'tooltip' => ['trigger' => 'axis', 'axisPointer' => ['type' => 'shadow']],
            'legend' => ['show' => count($series) > 1, 'top' => 'bottom'],
            'grid' => ['left' => 50, 'right' => 30, 'top' => $this->hasTitle($input) ? 50 : 20, 'bottom' => count($series) > 1 ? 40 : 20, 'containLabel' => true],
            'xAxis' => $vertical ? $categoryAxis : $valueAxis,
            'yAxis' => $vertical ? $valueAxis : $categoryAxis,
            'series' => $series,
        ];
    }
}
