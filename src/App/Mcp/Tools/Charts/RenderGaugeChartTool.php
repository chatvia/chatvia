<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Charts;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Gauge chart: one value pointed at on a min–max scale.
 */
#[Description(<<<'TXT'
Render an interactive gauge (speedometer-style) chart for a single metric
against a scale. Use for progress, completion, KPI status, scores:
"73% project completion", "API health 92/100", "SLA 4.2 / 5".

Inputs:
  - value: the number to point at.
  - min / max: scale bounds (default 0 to 100).
  - unit: optional suffix shown after the value (e.g. "%", " req/s").
  - title: the metric name shown above.

Only one value per gauge — for multiple metrics, render multiple gauges in
separate tool calls or pick a different chart type.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
class RenderGaugeChartTool extends EChartsTool
{
    protected const string TITLE_HINT = 'Optional metric name shown above the gauge.';

    protected const string SUMMARY_HINT = 'One sentence describing what the gauge shows.';

    protected function chartRules(): array
    {
        return [
            'value' => ['required', 'numeric'],
            'min' => ['nullable', 'numeric'],
            'max' => ['nullable', 'numeric'],
            'unit' => ['nullable', 'string', 'max:20'],
        ];
    }

    protected function chartSchema(JsonSchema $schema): array
    {
        return [
            'value' => $schema->number()
                ->description('The number the gauge points at, e.g. 73.')
                ->required(),

            'min' => $schema->number()->description('Lower bound of the scale. Default 0.')->default(0),
            'max' => $schema->number()->description('Upper bound of the scale. Default 100.')->default(100),
            'unit' => $schema->string()->description('Optional suffix shown after the value, e.g. "%" or " req/s".'),
        ];
    }

    protected function option(array $input): array
    {
        return [
            'series' => [[
                'type' => 'gauge',
                'min' => $input['min'] ?? 0,
                'max' => $input['max'] ?? 100,
                'progress' => ['show' => true, 'width' => 18],
                'axisLine' => ['lineStyle' => ['width' => 18]],
                'pointer' => ['show' => true],
                'detail' => [
                    'valueAnimation' => true,
                    'formatter' => '{value}'.($input['unit'] ?? ''),
                    'offsetCenter' => [0, '70%'],
                    'fontSize' => 24,
                ],
                'data' => [['value' => $input['value'], 'name' => $input['title'] ?? '']],
            ]],
        ];
    }
}
