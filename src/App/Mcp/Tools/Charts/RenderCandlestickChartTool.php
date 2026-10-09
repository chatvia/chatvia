<?php

namespace App\Mcp\Tools\Charts;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Candlestick (OHLC) chart for financial time-series, with zoom and pan.
 */
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
class RenderCandlestickChartTool extends EChartsTool
{
    protected const string TITLE_HINT = 'Optional chart title, e.g. "BTC/USD Daily".';

    protected const string SUMMARY_HINT = 'One sentence describing what the chart shows. The LLM sees this on follow-up turns; users see the chart, not this text.';

    protected const array AXIS_LABELS = [
        'yAxisLabel' => 'Optional y-axis label, e.g. "Price (USD)".',
    ];

    /** Above this many candles the chart opens zoomed in on the most recent ones. */
    private const int INITIAL_WINDOW = 60;

    private const string UP_COLOR = '#26a69a';

    private const string DOWN_COLOR = '#ef5350';

    protected function chartRules(): array
    {
        return [
            'data' => ['required', 'array', 'min:1'],
            'data.*.date' => ['required', 'string'],
            'data.*.open' => ['required', 'numeric'],
            'data.*.high' => ['required', 'numeric'],
            'data.*.low' => ['required', 'numeric'],
            'data.*.close' => ['required', 'numeric'],
            'showSlider' => ['nullable', 'boolean'],
        ];
    }

    protected function chartSchema(JsonSchema $schema): array
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
        ];
    }

    protected function option(array $input): array
    {
        $dates = array_map(static fn (array $candle): mixed => $candle['date'], $input['data']);

        // ECharts wants [open, close, low, high] — not the conventional OHLC
        // order — so the swap happens here and callers keep the usual shape.
        $candles = array_map(static fn (array $candle): array => [
            (float) $candle['open'],
            (float) $candle['close'],
            (float) $candle['low'],
            (float) $candle['high'],
        ], $input['data']);

        $showSlider = $input['showSlider'] ?? true;

        return [
            'tooltip' => ['trigger' => 'axis', 'axisPointer' => ['type' => 'cross']],
            'grid' => [
                'left' => 60,
                'right' => 30,
                'top' => $this->hasTitle($input) ? 50 : 20,
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
            'yAxis' => $this->withAxisName(['type' => 'value', 'scale' => true, 'splitArea' => ['show' => true]], $input, 'yAxisLabel'),
            'dataZoom' => $this->dataZoom(count($dates), $showSlider),
            'series' => [[
                'type' => 'candlestick',
                'name' => 'OHLC',
                'data' => $candles,
                'itemStyle' => [
                    'color' => self::UP_COLOR,
                    'color0' => self::DOWN_COLOR,
                    'borderColor' => self::UP_COLOR,
                    'borderColor0' => self::DOWN_COLOR,
                ],
            ]],
        ];
    }

    /**
     * Long series open on their most recent candles: less to paint at first
     * and the recent prices people usually ask about stay in view.
     *
     * @return list<array<string, mixed>>
     */
    private function dataZoom(int $totalPoints, bool $showSlider): array
    {
        $start = $totalPoints > self::INITIAL_WINDOW
            ? max(0, 100 - (int) round((self::INITIAL_WINDOW / $totalPoints) * 100))
            : 0;

        $dataZoom = [['type' => 'inside', 'start' => $start, 'end' => 100]];
        if ($showSlider) {
            $dataZoom[] = ['type' => 'slider', 'start' => $start, 'end' => 100, 'bottom' => 10, 'height' => 28];
        }

        return $dataZoom;
    }
}
