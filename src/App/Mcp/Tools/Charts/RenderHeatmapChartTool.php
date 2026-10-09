<?php

namespace App\Mcp\Tools\Charts;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Heatmap: intensity per (x, y) cell, with cells named by category label.
 */
#[Description(<<<'TXT'
Render an interactive 2D heatmap. Use for matrix-style intensity data:
"temperature by month and hour of day", "page views by day-of-week and hour",
"conversion rate by source × campaign".

Inputs:
  - xCategories: labels along the bottom axis.
  - yCategories: labels along the left axis.
  - data: cells as {x, y, value} where x and y refer to category NAMES
    (not indices). Missing cells render as empty — fine to omit them.
  - minValue / maxValue: optional color scale bounds. Auto-computed if omitted.

The chart auto-colors low → high using a sequential palette. Sparse matrices
are fine; only include the cells you have data for.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
class RenderHeatmapChartTool extends EChartsTool
{
    /** Cell values are printed on the chart only while they stay readable. */
    private const int MAX_LABELLED_CELLS = 80;

    protected function chartRules(): array
    {
        return [
            'xCategories' => ['required', 'array', 'min:1'],
            'xCategories.*' => ['required', 'string'],
            'yCategories' => ['required', 'array', 'min:1'],
            'yCategories.*' => ['required', 'string'],
            'data' => ['required', 'array', 'min:1'],
            'data.*.x' => ['required', 'string'],
            'data.*.y' => ['required', 'string'],
            'data.*.value' => ['required', 'numeric'],
            'minValue' => ['nullable', 'numeric'],
            'maxValue' => ['nullable', 'numeric'],
        ];
    }

    protected function chartSchema(JsonSchema $schema): array
    {
        return [
            'xCategories' => $schema->array()
                ->items($schema->string())
                ->description('Labels along the bottom axis, e.g. ["00:00","01:00",...,"23:00"].')
                ->required(),

            'yCategories' => $schema->array()
                ->items($schema->string())
                ->description('Labels along the left axis, e.g. ["Jan","Feb",...,"Dec"].')
                ->required(),

            'data' => $schema->array()
                ->items($schema->object([
                    'x' => $schema->string()->description('Must be one of the entries in xCategories.')->required(),
                    'y' => $schema->string()->description('Must be one of the entries in yCategories.')->required(),
                    'value' => $schema->number()->description('Intensity at this cell. Color is computed from min/max.')->required(),
                ]))
                ->description('Cells. Refer to categories by name; the tool maps to indices.')
                ->required(),

            'minValue' => $schema->number()->description('Optional lower bound for the color scale. Defaults to the minimum value in `data`.'),
            'maxValue' => $schema->number()->description('Optional upper bound for the color scale. Defaults to the maximum value in `data`.'),
        ];
    }

    protected function option(array $input): array
    {
        [$points, $values] = $this->cells($input);

        return [
            'tooltip' => ['position' => 'top'],
            'grid' => ['top' => $this->hasTitle($input) ? 50 : 30, 'left' => 80, 'right' => 30, 'bottom' => 60, 'containLabel' => true],
            'xAxis' => ['type' => 'category', 'data' => $input['xCategories'], 'splitArea' => ['show' => true]],
            'yAxis' => ['type' => 'category', 'data' => $input['yCategories'], 'splitArea' => ['show' => true]],
            'visualMap' => [
                'min' => $input['minValue'] ?? ($values !== [] ? min($values) : 0),
                'max' => $input['maxValue'] ?? ($values !== [] ? max($values) : 1),
                'calculable' => true,
                'orient' => 'horizontal',
                'left' => 'center',
                'bottom' => 10,
            ],
            'series' => [[
                'type' => 'heatmap',
                'data' => $points,
                'label' => ['show' => count($points) <= self::MAX_LABELLED_CELLS],
                'emphasis' => ['itemStyle' => ['shadowBlur' => 10, 'shadowColor' => 'rgba(0,0,0,0.5)']],
            ]],
        ];
    }

    /**
     * Map each named cell to ECharts' [xIndex, yIndex, value] triple.
     *
     * @param  array<string, mixed>  $input
     * @return array{0: list<array{0: int, 1: int, 2: mixed}>, 1: list<float>}
     *
     * @throws InvalidChartInputException when a cell names an unknown category.
     */
    private function cells(array $input): array
    {
        $xIndex = array_flip($input['xCategories']);
        $yIndex = array_flip($input['yCategories']);

        $points = [];
        $values = [];
        foreach ($input['data'] as $i => $cell) {
            if (! isset($xIndex[$cell['x']])) {
                throw new InvalidChartInputException("data[{$i}].x = \"{$cell['x']}\" is not in xCategories.");
            }
            if (! isset($yIndex[$cell['y']])) {
                throw new InvalidChartInputException("data[{$i}].y = \"{$cell['y']}\" is not in yCategories.");
            }
            $points[] = [$xIndex[$cell['x']], $yIndex[$cell['y']], $cell['value']];
            $values[] = (float) $cell['value'];
        }

        return [$points, $values];
    }
}
