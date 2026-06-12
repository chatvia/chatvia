<?php

namespace Domain\Analytics\Services\Chart;

/**
 * Builds Apache ECharts `option` arrays for the analytics tools to embed in
 * their results so a chart renders automatically — the chat's
 * AnalyticsToolDispatcher promotes `structuredContent.echarts_option` to an
 * `EChartBlock` artifact. The shapes mirror the Charts MCP server's
 * Render*ChartTool output exactly, so the same frontend renderer + theme apply.
 * No colors are set — the frontend's dark/light theme provider supplies them.
 */
final class AnalyticsChart
{
    /**
     * @param  list<string>  $categories
     * @param  list<array{name: string, data: list<int|float>}>  $series
     */
    public static function bar(array $categories, array $series, ?string $title = null, bool $horizontal = false): array
    {
        $categoryAxis = ['type' => 'category', 'data' => array_values($categories)];
        $valueAxis = ['type' => 'value'];

        $built = array_map(static fn (array $s): array => [
            'type' => 'bar',
            'name' => $s['name'],
            'data' => array_values($s['data']),
        ], array_values($series));

        return self::withTitle([
            'tooltip' => ['trigger' => 'axis', 'axisPointer' => ['type' => 'shadow']],
            'legend' => ['show' => count($built) > 1, 'top' => 'bottom'],
            'grid' => ['left' => 50, 'right' => 30, 'top' => $title ? 50 : 20, 'bottom' => count($built) > 1 ? 40 : 20, 'containLabel' => true],
            'xAxis' => $horizontal ? $valueAxis : $categoryAxis,
            'yAxis' => $horizontal ? $categoryAxis : $valueAxis,
            'series' => $built,
        ], $title);
    }

    /**
     * Single-series bar from `label => value` pairs.
     *
     * @param  array<string, int|float>  $values
     */
    public static function barFromMap(array $values, string $seriesName, ?string $title = null, bool $horizontal = false): array
    {
        return self::bar(array_keys($values), [['name' => $seriesName, 'data' => array_values($values)]], $title, $horizontal);
    }

    /**
     * @param  list<string>  $categories
     * @param  list<array{name: string, data: list<int|float>}>  $series
     */
    public static function line(array $categories, array $series, ?string $title = null): array
    {
        $built = array_map(static fn (array $s): array => [
            'type' => 'line',
            'name' => $s['name'],
            'data' => array_values($s['data']),
        ], array_values($series));

        return self::withTitle([
            'tooltip' => ['trigger' => 'axis'],
            'legend' => ['show' => count($built) > 1, 'top' => 'bottom'],
            'grid' => ['left' => 50, 'right' => 30, 'top' => $title ? 50 : 20, 'bottom' => count($built) > 1 ? 40 : 20, 'containLabel' => true],
            'xAxis' => ['type' => 'category', 'data' => array_map('strval', array_values($categories)), 'boundaryGap' => false],
            'yAxis' => ['type' => 'value'],
            'series' => $built,
        ], $title);
    }

    /**
     * @param  list<array{name: string, value: int|float}>  $slices
     */
    public static function pie(array $slices, ?string $title = null, bool $donut = false): array
    {
        return self::withTitle([
            'tooltip' => ['trigger' => 'item', 'formatter' => '{b}: {c} ({d}%)'],
            'legend' => ['show' => true, 'top' => 'bottom'],
            'series' => [[
                'type' => 'pie',
                'radius' => $donut ? ['40%', '70%'] : '70%',
                'avoidLabelOverlap' => true,
                'label' => ['show' => true, 'formatter' => '{b}: {d}%'],
                'data' => array_map(static fn (array $s): array => ['name' => $s['name'], 'value' => $s['value']], array_values($slices)),
            ]],
        ], $title);
    }

    public static function gauge(int|float $value, int|float $min = 0, int|float $max = 100, ?string $unit = null, ?string $title = null): array
    {
        return self::withTitle([
            'series' => [[
                'type' => 'gauge',
                'min' => $min,
                'max' => $max,
                'progress' => ['show' => true, 'width' => 18],
                'axisLine' => ['lineStyle' => ['width' => 18]],
                'pointer' => ['show' => true],
                'detail' => [
                    'valueAnimation' => true,
                    'formatter' => '{value}'.($unit ?? ''),
                    'offsetCenter' => [0, '70%'],
                    'fontSize' => 24,
                ],
                'data' => [['value' => $value, 'name' => $title ?? '']],
            ]],
        ], $title);
    }

    /**
     * @param  array<string, mixed>  $option
     * @return array<string, mixed>
     */
    private static function withTitle(array $option, ?string $title): array
    {
        if ($title !== null && $title !== '') {
            $option['title'] = ['text' => $title, 'left' => 'center'];
        }

        return $option;
    }
}
