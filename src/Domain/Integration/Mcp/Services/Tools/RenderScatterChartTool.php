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
Render an interactive scatter or bubble chart. Use for distributions and
two-variable correlations: "GDP vs life expectancy", "user age vs session
length", "company revenue vs employee count".

Inputs:
  - series: one or more groups of points. Each point is {x, y}. Add a `size`
    field to any point to make it a bubble chart (point area scales with size).
  - xAxisLabel / yAxisLabel: optional axis names.

If you pass `size` on points, you're making a bubble chart — typically used
when you want a third dimension (population, market cap, etc.).
TXT)]
#[IsReadOnly]
#[IsIdempotent]
class RenderScatterChartTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $v = $request->validate([
            'series' => ['required', 'array', 'min:1'],
            'series.*.name' => ['required', 'string'],
            'series.*.points' => ['required', 'array', 'min:1'],
            'series.*.points.*.x' => ['required', 'numeric'],
            'series.*.points.*.y' => ['required', 'numeric'],
            'series.*.points.*.size' => ['nullable', 'numeric', 'min:0'],
            'series.*.points.*.label' => ['nullable', 'string'],
            'title' => ['nullable', 'string', 'max:120'],
            'xAxisLabel' => ['nullable', 'string', 'max:60'],
            'yAxisLabel' => ['nullable', 'string', 'max:60'],
            'summary' => ['required', 'string', 'max:500'],
        ]);

        $maxSize = 0.0;
        foreach ($v['series'] as $s) {
            foreach ($s['points'] as $p) {
                if (isset($p['size']) && (float) $p['size'] > $maxSize) {
                    $maxSize = (float) $p['size'];
                }
            }
        }

        $series = array_map(function (array $s) use ($maxSize): array {
            $data = array_map(function (array $p) use ($maxSize): array {
                $point = ['value' => [$p['x'], $p['y']]];
                if (! empty($p['label'])) {
                    $point['name'] = $p['label'];
                }
                if ($maxSize > 0 && isset($p['size'])) {
                    $point['symbolSize'] = sqrt(((float) $p['size']) / $maxSize) * 40 + 4;
                }

                return $point;
            }, $s['points']);

            return ['type' => 'scatter', 'name' => $s['name'], 'data' => $data];
        }, $v['series']);

        $xAxis = ['type' => 'value'];
        if (! empty($v['xAxisLabel'])) {
            $xAxis['name'] = $v['xAxisLabel'];
        }
        $yAxis = ['type' => 'value'];
        if (! empty($v['yAxisLabel'])) {
            $yAxis['name'] = $v['yAxisLabel'];
        }

        $option = [
            'tooltip' => ['trigger' => 'item'],
            'legend' => ['show' => count($series) > 1, 'top' => 'bottom'],
            'grid' => ['left' => 50, 'right' => 30, 'top' => ! empty($v['title']) ? 50 : 20, 'bottom' => 40, 'containLabel' => true],
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
            'series' => $schema->array()
                ->items($schema->object([
                    'name' => $schema->string()->description('Group/series name shown in legend.')->required(),
                    'points' => $schema->array()
                        ->items($schema->object([
                            'x' => $schema->number()->description('X coordinate.')->required(),
                            'y' => $schema->number()->description('Y coordinate.')->required(),
                            'size' => $schema->number()->description('Optional bubble size value (relative magnitude — the renderer normalizes across all points).'),
                            'label' => $schema->string()->description('Optional per-point label shown in tooltip.'),
                        ]))
                        ->description('Points in this series.')
                        ->required(),
                ]))
                ->description('One or more scatter series. Different series get different colors.')
                ->required(),

            'title' => $schema->string()->description('Optional chart title.'),
            'xAxisLabel' => $schema->string()->description('Optional x-axis label.'),
            'yAxisLabel' => $schema->string()->description('Optional y-axis label.'),

            'summary' => $schema->string()
                ->description('One sentence describing what the chart shows.')
                ->required(),
        ];
    }
}
