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
Render an interactive treemap — hierarchical data as nested rectangles sized
by value. Use for hierarchical share-of-whole views: "companies by sector and
market cap", "files by folder and disk size", "expenses by category and
sub-category".

Inputs:
  - data: an array of nodes. Each node is {name, value?, children?}.
    Leaves have `value`. Parent nodes can have `value` (sums of children) or
    omit it and ECharts will sum the children automatically.
    Nesting can go arbitrarily deep — `children` contains the same shape.

Click rectangles to drill in. Click the breadcrumb at the top to zoom out.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
class RenderTreemapChartTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $v = $request->validate([
            'data' => ['required', 'array', 'min:1'],
            'title' => ['nullable', 'string', 'max:120'],
            'summary' => ['required', 'string', 'max:500'],
        ]);

        $normalized = $this->normalize($v['data']);

        $option = [
            'tooltip' => ['formatter' => '{b}: {c}'],
            'series' => [[
                'type' => 'treemap',
                'data' => $normalized,
                'roam' => false,
                'leafDepth' => 2,
                'label' => ['show' => true, 'formatter' => '{b}'],
                'upperLabel' => ['show' => true, 'height' => 24],
                'levels' => [
                    ['itemStyle' => ['borderColor' => '#fff', 'borderWidth' => 2, 'gapWidth' => 2]],
                    ['itemStyle' => ['borderColor' => '#fff', 'borderWidth' => 1, 'gapWidth' => 1]],
                    ['itemStyle' => ['borderColor' => '#fff', 'borderWidth' => 1, 'gapWidth' => 1]],
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
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int, array<string, mixed>>
     */
    private function normalize(array $nodes): array
    {
        $out = [];
        foreach ($nodes as $node) {
            if (! is_array($node) || ! isset($node['name'])) {
                continue;
            }
            $entry = ['name' => (string) $node['name']];
            if (isset($node['value']) && is_numeric($node['value'])) {
                $entry['value'] = $node['value'];
            }
            if (! empty($node['children']) && is_array($node['children'])) {
                $entry['children'] = $this->normalize($node['children']);
            }
            $out[] = $entry;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'data' => $schema->array()
                ->items($schema->object([
                    'name' => $schema->string()->description('Node label.')->required(),
                    'value' => $schema->number()->description('Numeric size of this node. Required for leaves. For parents, can be omitted to use the sum of children.'),
                    'children' => $schema->array()->description('Optional array of child nodes with the same shape. Nest arbitrarily deep.'),
                ]))
                ->description('Top-level nodes. Each node may contain `children` of the same shape, recursively.')
                ->required(),

            'title' => $schema->string()->description('Optional chart title.'),

            'summary' => $schema->string()
                ->description('One sentence describing what the chart shows.')
                ->required(),
        ];
    }
}
