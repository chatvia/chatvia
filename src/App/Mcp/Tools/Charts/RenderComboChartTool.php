<?php

namespace App\Mcp\Tools\Charts;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Combo chart: bars on the left y-axis and lines on an independent right one.
 */
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
class RenderComboChartTool extends EChartsTool
{
    protected const string TITLE_HINT = 'Optional chart title shown at the top.';

    protected const array AXIS_LABELS = [
        'leftAxisLabel' => 'Optional name for the LEFT y-axis (bar series).',
        'rightAxisLabel' => 'Optional name for the RIGHT y-axis (line series).',
    ];

    protected function chartRules(): array
    {
        return [
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
        ];
    }

    protected function chartSchema(JsonSchema $schema): array
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
        ];
    }

    protected function option(array $input): array
    {
        $bars = array_map(static fn (array $series): array => [
            'type' => 'bar',
            'name' => $series['name'],
            'data' => $series['data'],
            'yAxisIndex' => 0,
        ], $input['barSeries']);

        $lines = array_map(static fn (array $series): array => [
            'type' => 'line',
            'name' => $series['name'],
            'data' => $series['data'],
            'yAxisIndex' => 1,
            'smooth' => true,
        ], $input['lineSeries']);

        return [
            'tooltip' => ['trigger' => 'axis'],
            'legend' => ['show' => true, 'top' => 'bottom'],
            'grid' => ['left' => 60, 'right' => 60, 'top' => $this->hasTitle($input) ? 50 : 20, 'bottom' => 40, 'containLabel' => true],
            'xAxis' => ['type' => 'category', 'data' => $input['categories']],
            'yAxis' => [
                $this->withAxisName(['type' => 'value', 'position' => 'left'], $input, 'leftAxisLabel'),
                $this->withAxisName(['type' => 'value', 'position' => 'right'], $input, 'rightAxisLabel'),
            ],
            'series' => array_merge($bars, $lines),
        ];
    }
}
