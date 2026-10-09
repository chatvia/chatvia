<?php

namespace App\Mcp\Tools\Charts;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Sankey diagram: flows between named nodes, sized by value.
 */
#[Description(<<<'TXT'
Render an interactive Sankey diagram for flow visualization. Use when showing
how a total splits or recombines through stages: "budget flow from revenue
through expenses to profit", "energy flow from sources to consumption",
"user journey from landing → signup → activation → churn/retain".

Inputs:
  - links: each is {source, target, value}. Source and target are NODE NAMES.
    Value is the magnitude of flow.
  - nodes: optional explicit node list as {name}. If omitted, nodes are
    auto-derived from the unique source/target names in links.

The diagram must be acyclic — A → B → A would break the layout.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
class RenderSankeyChartTool extends EChartsTool
{
    protected const string SUMMARY_HINT = 'One sentence describing what the diagram shows.';

    protected function chartRules(): array
    {
        return [
            'links' => ['required', 'array', 'min:1'],
            'links.*.source' => ['required', 'string'],
            'links.*.target' => ['required', 'string'],
            'links.*.value' => ['required', 'numeric', 'min:0'],
            'nodes' => ['nullable', 'array'],
            'nodes.*.name' => ['required_with:nodes', 'string'],
        ];
    }

    protected function chartSchema(JsonSchema $schema): array
    {
        return [
            'links' => $schema->array()
                ->items($schema->object([
                    'source' => $schema->string()->description('Source node name. Must match a node either in `nodes` or one auto-derived from another link.')->required(),
                    'target' => $schema->string()->description('Target node name. Must match a node either in `nodes` or one auto-derived from another link.')->required(),
                    'value' => $schema->number()->description('Flow magnitude — ribbon thickness scales with this value.')->required(),
                ]))
                ->description('All flows in the diagram. Nodes are auto-derived from these unless `nodes` is provided.')
                ->required(),

            'nodes' => $schema->array()
                ->items($schema->object([
                    'name' => $schema->string()->description('Node label.')->required(),
                ]))
                ->description('Optional explicit list of nodes. Use this when you want to order nodes or include isolated nodes (no links).'),
        ];
    }

    protected function option(array $input): array
    {
        return [
            'tooltip' => ['trigger' => 'item', 'triggerOn' => 'mousemove'],
            'series' => [[
                'type' => 'sankey',
                'data' => $this->nodes($input),
                'links' => array_map(static fn (array $link): array => ['source' => $link['source'], 'target' => $link['target'], 'value' => $link['value']], $input['links']),
                'emphasis' => ['focus' => 'adjacency'],
                'lineStyle' => ['color' => 'gradient', 'curveness' => 0.5],
                'label' => ['fontSize' => 11],
            ]],
        ];
    }

    /**
     * The explicit node list when given, otherwise every name the links use,
     * in first-seen order.
     *
     * @param  array<string, mixed>  $input
     * @return list<array{name: mixed}>
     */
    private function nodes(array $input): array
    {
        if (! empty($input['nodes'])) {
            return array_map(static fn (array $node): array => ['name' => $node['name']], $input['nodes']);
        }

        $names = [];
        foreach ($input['links'] as $link) {
            $names[$link['source']] = true;
            $names[$link['target']] = true;
        }

        return array_map(static fn (int|string $name): array => ['name' => $name], array_keys($names));
    }
}
