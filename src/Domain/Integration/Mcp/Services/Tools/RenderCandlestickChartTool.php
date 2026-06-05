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
Render an interactive candlestick (OHLC) chart for financial time-series.
Use for stock prices, crypto, forex, or any time-bucketed open/high/low/close
data: "Bitcoin daily closes for the last 2 years", "AAPL weekly OHLC for
Q4", "EUR/USD hourly".

Inputs:
  - data: each entry is {date, open, high, low, close} — standard OHLC order.
    The tool converts to ECharts's internal [open, close, low, high] order
    for you, so don't worry about it.
  - title / yAxisLabel: optional.
  - showSlider: default true. The chart always supports wheel/pinch zoom; the
    slider adds a visible drag handle below.

Up candles (close ≥ open) render green; down candles render red. Built-in
zoom + pan via dataZoom so 500+ point series stay readable.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
class RenderCandlestickChartTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $v = $request->validate([
            'data' => ['required', 'array', 'min:1'],
            'data.*.date' => ['required', 'string'],
            'data.*.open' => ['required', 'numeric'],
            'data.*.high' => ['required', 'numeric'],
            'data.*.low' => ['required', 'numeric'],
            'data.*.close' => ['required', 'numeric'],
            'showSlider' => ['nullable', 'boolean'],
            'title' => ['nullable', 'string', 'max:120'],
            'yAxisLabel' => ['nullable', 'string', 'max:60'],
            'summary' => ['required', 'string', 'max:500'],
        ]);

        $dates = array_map(fn ($d) => $d['date'], $v['data']);

        // ECharts candlestick wants [open, close, low, high] in this exact
        // order — distinct from the conventional OHLC [open, high, low,
        // close]. The Action does the swap so the LLM/caller can stick
        // with the conventional shape.
        $candles = array_map(fn ($d) => [
            (float) $d['open'],
            (float) $d['close'],
            (float) $d['low'],
            (float) $d['high'],
        ], $v['data']);

        $showSlider = $v['showSlider'] ?? true;

        // Default start window: last 30% of the series. Less to render on
        // first paint and keeps recent prices visible by default for the
        // common "last N months" question.
        $totalPoints = count($dates);
        $defaultStart = $totalPoints > 60 ? max(0, 100 - (int) round((60 / $totalPoints) * 100)) : 0;

        $dataZoom = [['type' => 'inside', 'start' => $defaultStart, 'end' => 100]];
        if ($showSlider) {
            $dataZoom[] = ['type' => 'slider', 'start' => $defaultStart, 'end' => 100, 'bottom' => 10, 'height' => 28];
        }

        $yAxis = ['type' => 'value', 'scale' => true, 'splitArea' => ['show' => true]];
        if (! empty($v['yAxisLabel'])) {
            $yAxis['name'] = $v['yAxisLabel'];
        }

        $option = [
            'tooltip' => ['trigger' => 'axis', 'axisPointer' => ['type' => 'cross']],
            'grid' => [
                'left' => 60,
                'right' => 30,
                'top' => ! empty($v['title']) ? 50 : 20,
                'bottom' => $showSlider ? 70 : 30,
                'containLabel' => true,
            ],
            'xAxis' => [
                'type' => 'category',
                'data' => $dates,
                'scale' => true,
                'boundaryGap' => false,
                'axisLine' => ['onZero' => false],
                'splitLine' => ['show' => false],
            ],
            'yAxis' => $yAxis,
            'dataZoom' => $dataZoom,
            'series' => [[
                'type' => 'candlestick',
                'name' => 'OHLC',
                'data' => $candles,
                'itemStyle' => [
                    'color' => '#26a69a',
                    'color0' => '#ef5350',
                    'borderColor' => '#26a69a',
                    'borderColor0' => '#ef5350',
                ],
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
            'data' => $schema->array()
                ->items($schema->object([
                    'date' => $schema->string()->description('Time bucket label, e.g. "2024-01-15" or "Q1 2025". Used as the x-axis category.')->required(),
                    'open' => $schema->number()->description('Opening price.')->required(),
                    'high' => $schema->number()->description('Highest price during the period.')->required(),
                    'low' => $schema->number()->description('Lowest price during the period.')->required(),
                    'close' => $schema->number()->description('Closing price.')->required(),
                ]))
                ->description('Time-ordered OHLC candles. Use the standard {open, high, low, close} order — the tool handles ECharts\'s internal swap.')
                ->required(),

            'showSlider' => $schema->boolean()
                ->description('Show the visible zoom slider below the chart. Default true. Set false for a cleaner look when the user wants the chart to stay fixed; mouse-wheel zoom still works either way.')
                ->default(true),

            'title' => $schema->string()->description('Optional chart title, e.g. "BTC/USD Daily".'),
            'yAxisLabel' => $schema->string()->description('Optional y-axis label, e.g. "Price (USD)".'),

            'summary' => $schema->string()
                ->description('One sentence describing what the chart shows. The LLM sees this on follow-up turns; users see the chart, not this text.')
                ->required(),
        ];
    }
}
