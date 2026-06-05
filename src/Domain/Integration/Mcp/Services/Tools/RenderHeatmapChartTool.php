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
class RenderHeatmapChartTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $v = $request->validate([
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
            'title' => ['nullable', 'string', 'max:120'],
            'summary' => ['required', 'string', 'max:500'],
        ]);

        $xIndex = array_flip($v['xCategories']);
        $yIndex = array_flip($v['yCategories']);

        $points = [];
        $values = [];
        foreach ($v['data'] as $i => $cell) {
            if (! isset($xIndex[$cell['x']])) {
                return Response::error("data[{$i}].x = \"{$cell['x']}\" is not in xCategories.");
            }
            if (! isset($yIndex[$cell['y']])) {
                return Response::error("data[{$i}].y = \"{$cell['y']}\" is not in yCategories.");
            }
            $points[] = [$xIndex[$cell['x']], $yIndex[$cell['y']], $cell['value']];
            $values[] = (float) $cell['value'];
        }

        $min = $v['minValue'] ?? ($values !== [] ? min($values) : 0);
        $max = $v['maxValue'] ?? ($values !== [] ? max($values) : 1);

        $option = [
            'tooltip' => ['position' => 'top'],
            'grid' => ['top' => ! empty($v['title']) ? 50 : 30, 'left' => 80, 'right' => 30, 'bottom' => 60, 'containLabel' => true],
            'xAxis' => ['type' => 'category', 'data' => $v['xCategories'], 'splitArea' => ['show' => true]],
            'yAxis' => ['type' => 'category', 'data' => $v['yCategories'], 'splitArea' => ['show' => true]],
            'visualMap' => [
                'min' => $min,
                'max' => $max,
                'calculable' => true,
                'orient' => 'horizontal',
                'left' => 'center',
                'bottom' => 10,
            ],
            'series' => [[
                'type' => 'heatmap',
                'data' => $points,
                'label' => ['show' => count($points) <= 80],
                'emphasis' => ['itemStyle' => ['shadowBlur' => 10, 'shadowColor' => 'rgba(0,0,0,0.5)']],
            ]],
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
            'title' => $schema->string()->description('Optional chart title.'),

            'summary' => $schema->string()
                ->description('One sentence describing what the chart shows.')
                ->required(),
        ];
    }
}
