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
Render an interactive pie or donut chart. Use for share-of-whole comparisons:
"market share by browser", "expense breakdown", "votes per candidate".

Inputs:
  - data: array of {name, value} slices.
  - donut: when true, leaves a hole in the middle (donut chart).

Keep the slice count below ~8 for readability. Long-tail categories should be
collapsed into an "Other" slice by the caller.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
class RenderPieChartTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $v = $request->validate([
            'data' => ['required', 'array', 'min:1'],
            'data.*.name' => ['required', 'string'],
            'data.*.value' => ['required', 'numeric'],
            'donut' => ['nullable', 'boolean'],
            'title' => ['nullable', 'string', 'max:120'],
            'summary' => ['required', 'string', 'max:500'],
        ]);

        $series = [
            'type' => 'pie',
            'radius' => ! empty($v['donut']) ? ['40%', '70%'] : '70%',
            'avoidLabelOverlap' => true,
            'label' => ['show' => true, 'formatter' => '{b}: {d}%'],
            'data' => array_map(fn ($d): array => ['name' => $d['name'], 'value' => $d['value']], $v['data']),
        ];

        $option = [
            'tooltip' => ['trigger' => 'item', 'formatter' => '{b}: {c} ({d}%)'],
            'legend' => ['show' => true, 'top' => 'bottom'],
            'series' => [$series],
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
                    'name' => $schema->string()->description('Slice label.')->required(),
                    'value' => $schema->number()->description('Slice magnitude — percentages are computed automatically from these.')->required(),
                ]))
                ->description('Pie slices. Order doesn\'t matter; the chart sorts/colors them.')
                ->required(),

            'donut' => $schema->boolean()
                ->description('If true, render as a donut (hole in the middle). Default false.')
                ->default(false),

            'title' => $schema->string()->description('Optional chart title shown at the top.'),

            'summary' => $schema->string()
                ->description('One sentence describing what the chart shows.')
                ->required(),
        ];
    }
}
