<?php

namespace App\Mcp\Tools\Charts;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Scatter or bubble chart: points on two value axes, optionally sized.
 */
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
class RenderScatterChartTool extends EChartsTool
{
    protected const array AXIS_LABELS = [
        'xAxisLabel' => 'Optional x-axis label.',
        'yAxisLabel' => 'Optional y-axis label.',
    ];

    /** Bubble diameter range in pixels: the largest size maps to MIN + SPAN. */
    private const int MIN_BUBBLE = 4;

    private const int BUBBLE_SPAN = 40;

    protected function chartRules(): array
    {
        return [
            'series' => ['required', 'array', 'min:1'],
            'series.*.name' => ['required', 'string'],
            'series.*.points' => ['required', 'array', 'min:1'],
            'series.*.points.*.x' => ['required', 'numeric'],
            'series.*.points.*.y' => ['required', 'numeric'],
            'series.*.points.*.size' => ['nullable', 'numeric', 'min:0'],
            'series.*.points.*.label' => ['nullable', 'string'],
        ];
    }

    protected function chartSchema(JsonSchema $schema): array
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
        ];
    }

    protected function option(array $input): array
    {
        $maxSize = $this->largestSize($input['series']);

        $series = array_map(fn (array $series): array => [
            'type' => 'scatter',
            'name' => $series['name'],
            'data' => array_map(fn (array $point): array => $this->point($point, $maxSize), $series['points']),
        ], $input['series']);

        return [
            'tooltip' => ['trigger' => 'item'],
            'legend' => ['show' => count($series) > 1, 'top' => 'bottom'],
            'grid' => ['left' => 50, 'right' => 30, 'top' => $this->hasTitle($input) ? 50 : 20, 'bottom' => 40, 'containLabel' => true],
            'xAxis' => $this->withAxisName(['type' => 'value'], $input, 'xAxisLabel'),
            'yAxis' => $this->withAxisName(['type' => 'value'], $input, 'yAxisLabel'),
            'series' => $series,
        ];
    }

    /**
     * Bubble sizes are relative, so they are scaled against the largest one.
     *
     * @param  list<array<string, mixed>>  $series
     */
    private function largestSize(array $series): float
    {
        $maxSize = 0.0;
        foreach ($series as $group) {
            foreach ($group['points'] as $point) {
                if (isset($point['size']) && (float) $point['size'] > $maxSize) {
                    $maxSize = (float) $point['size'];
                }
            }
        }

        return $maxSize;
    }

    /**
     * @param  array<string, mixed>  $point
     * @return array<string, mixed>
     */
    private function point(array $point, float $maxSize): array
    {
        $item = ['value' => [$point['x'], $point['y']]];
        if (! empty($point['label'])) {
            $item['name'] = $point['label'];
        }
        // Area, not diameter, tracks the value — hence the square root.
        if ($maxSize > 0 && isset($point['size'])) {
            $item['symbolSize'] = sqrt(((float) $point['size']) / $maxSize) * self::BUBBLE_SPAN + self::MIN_BUBBLE;
        }

        return $item;
    }
}
