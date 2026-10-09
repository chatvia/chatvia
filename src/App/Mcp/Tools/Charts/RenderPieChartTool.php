<?php

namespace App\Mcp\Tools\Charts;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Pie or donut chart: share of a whole.
 */
#[Description(<<<'TXT'
Render an interactive pie or donut chart. Use for share-of-whole comparisons:
"market share by browser", "expense breakdown", "votes per candidate".

Inputs:
  - data: array of {name, value} slices.
  - donut: when true, leaves a hole in the middle (donut chart).

Keep the slice count below ~8 for readability. Long-tail categories should be
collapsed into an "Other" slice by the caller.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
class RenderPieChartTool extends EChartsTool
{
    protected const string TITLE_HINT = 'Optional chart title shown at the top.';

    protected function chartRules(): array
    {
        return [
            'data' => ['required', 'array', 'min:1'],
            'data.*.name' => ['required', 'string'],
            'data.*.value' => ['required', 'numeric'],
            'donut' => ['nullable', 'boolean'],
        ];
    }

    protected function chartSchema(JsonSchema $schema): array
    {
        return [
            'data' => $schema->array()
                ->items($schema->object([
                    'name' => $schema->string()->description('Slice label.')->required(),
                    'value' => $schema->number()->description('Slice magnitude — percentages are computed automatically from these.')->required(),
                ]))
                ->description('Pie slices. Order doesn\'t matter; the chart sorts/colors them.')
                ->required(),

            'donut' => $schema->boolean()
                ->description('If true, render as a donut (hole in the middle). Default false.')
                ->default(false),
        ];
    }

    protected function option(array $input): array
    {
        return [
            'tooltip' => ['trigger' => 'item', 'formatter' => '{b}: {c} ({d}%)'],
            'legend' => ['show' => true, 'top' => 'bottom'],
            'series' => [[
                'type' => 'pie',
                'radius' => ! empty($input['donut']) ? ['40%', '70%'] : '70%',
                'avoidLabelOverlap' => true,
                'label' => ['show' => true, 'formatter' => '{b}: {d}%'],
                'data' => array_map(static fn (array $slice): array => ['name' => $slice['name'], 'value' => $slice['value']], $input['data']),
            ]],
        ];
    }
}
