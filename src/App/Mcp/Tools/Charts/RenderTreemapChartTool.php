<?php

namespace App\Mcp\Tools\Charts;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Treemap: a hierarchy drawn as nested rectangles sized by value.
 */
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
class RenderTreemapChartTool extends EChartsTool
{
    use NormalizesTreeNodes;

    protected function chartRules(): array
    {
        return [
            'data' => ['required', 'array', 'min:1'],
        ];
    }

    protected function chartSchema(JsonSchema $schema): array
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
        ];
    }

    protected function option(array $input): array
    {
        return [
            'tooltip' => ['formatter' => '{b}: {c}'],
            'series' => [[
                'type' => 'treemap',
                'data' => $this->normalizeTree($input['data']),
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
    }
}
