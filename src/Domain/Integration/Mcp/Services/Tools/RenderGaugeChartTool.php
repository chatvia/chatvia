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
class RenderGaugeChartTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $v = $request->validate([
            'value' => ['required', 'numeric'],
            'min' => ['nullable', 'numeric'],
            'max' => ['nullable', 'numeric'],
            'unit' => ['nullable', 'string', 'max:20'],
            'title' => ['nullable', 'string', 'max:120'],
            'summary' => ['required', 'string', 'max:500'],
        ]);

        $min = $v['min'] ?? 0;
        $max = $v['max'] ?? 100;
        $unit = $v['unit'] ?? '';

        $option = [
            'series' => [[
                'type' => 'gauge',
                'min' => $min,
                'max' => $max,
                'progress' => ['show' => true, 'width' => 18],
                'axisLine' => ['lineStyle' => ['width' => 18]],
                'pointer' => ['show' => true],
                'detail' => [
                    'valueAnimation' => true,
                    'formatter' => '{value}'.$unit,
                    'offsetCenter' => [0, '70%'],
                    'fontSize' => 24,
                ],
                'data' => [['value' => $v['value'], 'name' => $v['title'] ?? '']],
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
            'value' => $schema->number()
                ->description('The number the gauge points at, e.g. 73.')
                ->required(),

            'min' => $schema->number()->description('Lower bound of the scale. Default 0.')->default(0),
            'max' => $schema->number()->description('Upper bound of the scale. Default 100.')->default(100),
            'unit' => $schema->string()->description('Optional suffix shown after the value, e.g. "%" or " req/s".'),
            'title' => $schema->string()->description('Optional metric name shown above the gauge.'),

            'summary' => $schema->string()
                ->description('One sentence describing what the gauge shows.')
                ->required(),
        ];
    }
}
