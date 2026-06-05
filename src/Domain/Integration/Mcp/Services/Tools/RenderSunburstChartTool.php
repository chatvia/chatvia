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
Render an interactive sunburst chart — hierarchical data as concentric rings.
Use when nesting is meaningful and you want both proportions and hierarchy
visible at once: "continents → countries → cities by population", "category
→ subcategory → product breakdown", "kingdom → phylum → class taxonomy".

Inputs:
  - data: an array of nodes. Each node is {name, value?, children?}.
    Leaves have `value`. Parents may have `value` (overrides sum of children)
    or omit it (auto-summed).
    Nesting is unlimited — `children` contains the same shape.

Click a wedge to zoom into its subtree. Click the center to zoom out.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
class RenderSunburstChartTool extends Tool
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
            'tooltip' => ['trigger' => 'item'],
            'series' => [[
                'type' => 'sunburst',
                'data' => $normalized,
                'radius' => [0, '90%'],
                'label' => ['rotate' => 'tangential', 'minAngle' => 5],
                'emphasis' => ['focus' => 'ancestor'],
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
                    'value' => $schema->number()->description('Numeric size of this node. Required for leaves.'),
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
