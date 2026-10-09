<?php

namespace App\Mcp\Tools\Charts;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Radar (spider) chart: several entities scored on the same set of axes.
 */
#[Description(<<<'TXT'
Render an interactive radar (spider) chart. Use for multi-axis comparisons
across the same set of dimensions: "iPhone vs Pixel across battery, camera,
performance", "player stats across attack, defense, speed, accuracy".

Inputs:
  - indicators: the axes radiating from the center. Each is {name, max}.
    Use `max` to set the upper bound per axis (allows different scales).
  - series: each entry is one shape on the chart — {name, values[]}.
    `values` must be aligned with `indicators` in length and order.

Lower-is-better axes (price, weight, latency): invert the values BEFORE passing
them in, or describe the axis as "inverse price" so the chart reads correctly.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
class RenderRadarChartTool extends EChartsTool
{
    protected function chartRules(): array
    {
        return [
            'indicators' => ['required', 'array', 'min:3'],
            'indicators.*.name' => ['required', 'string'],
            'indicators.*.max' => ['required', 'numeric', 'min:0'],
            'series' => ['required', 'array', 'min:1'],
            'series.*.name' => ['required', 'string'],
            'series.*.values' => ['required', 'array', 'min:3'],
            'series.*.values.*' => ['required', 'numeric'],
        ];
    }

    protected function chartSchema(JsonSchema $schema): array
    {
        return [
            'indicators' => $schema->array()
                ->items($schema->object([
                    'name' => $schema->string()->description('Axis label, e.g. "Battery life", "Camera quality".')->required(),
                    'max' => $schema->number()->description('Upper bound for this axis. Different axes can have different maxes (e.g. battery hours vs camera score 0-100).')->required(),
                ]))
                ->min(3)
                ->description('At least 3 axes. Each axis has its own scale via `max`.')
                ->required(),

            'series' => $schema->array()
                ->items($schema->object([
                    'name' => $schema->string()->description('Entity being scored, e.g. "iPhone 16 Pro".')->required(),
                    'values' => $schema->array()->items($schema->number())->description('Scores per indicator, in the same order as the `indicators` array.')->required(),
                ]))
                ->description('Each series is one polygon on the chart.')
                ->required(),
        ];
    }

    protected function option(array $input): array
    {
        $indicatorCount = count($input['indicators']);

        foreach ($input['series'] as $series) {
            if (count($series['values']) !== $indicatorCount) {
                throw new InvalidChartInputException(
                    "Series \"{$series['name']}\" has ".count($series['values'])." values but there are {$indicatorCount} indicators. Each series.values array must have one entry per indicator, in the same order."
                );
            }
        }

        return [
            'tooltip' => ['trigger' => 'item'],
            'legend' => ['show' => true, 'top' => 'bottom'],
            'radar' => [
                'indicator' => array_map(static fn (array $indicator): array => ['name' => $indicator['name'], 'max' => $indicator['max']], $input['indicators']),
            ],
            'series' => [[
                'type' => 'radar',
                'data' => array_map(static fn (array $series): array => ['name' => $series['name'], 'value' => $series['values']], $input['series']),
            ]],
        ];
    }
}
