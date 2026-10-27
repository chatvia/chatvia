<?php

namespace App\Mcp\Tools\Charts;

use App\Mcp\Tools\Charts\Concerns\NormalizesTreeNodes;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Sunburst: a hierarchy drawn as concentric rings.
 */
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
class RenderSunburstChartTool extends EChartsTool
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
                    'value' => $schema->number()->description('Numeric size of this node. Required for leaves.'),
                    'children' => $schema->array()->description('Optional array of child nodes with the same shape. Nest arbitrarily deep.'),
                ]))
                ->description('Top-level nodes. Each node may contain `children` of the same shape, recursively.')
                ->required(),
        ];
    }

    protected function option(array $input): array
    {
        return [
            'tooltip' => ['trigger' => 'item'],
            'series' => [[
                'type' => 'sunburst',
                'data' => $this->normalizeTree($input['data']),
                'radius' => [0, '90%'],
                'label' => ['rotate' => 'tangential', 'minAngle' => 5],
                'emphasis' => ['focus' => 'ancestor'],
            ]],
        ];
    }
}
