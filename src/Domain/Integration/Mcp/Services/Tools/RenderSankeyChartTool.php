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
class RenderSankeyChartTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $v = $request->validate([
            'links' => ['required', 'array', 'min:1'],
            'links.*.source' => ['required', 'string'],
            'links.*.target' => ['required', 'string'],
            'links.*.value' => ['required', 'numeric', 'min:0'],
            'nodes' => ['nullable', 'array'],
            'nodes.*.name' => ['required_with:nodes', 'string'],
            'title' => ['nullable', 'string', 'max:120'],
            'summary' => ['required', 'string', 'max:500'],
        ]);

        if (! empty($v['nodes'])) {
            $nodes = array_map(fn ($n): array => ['name' => $n['name']], $v['nodes']);
        } else {
            $names = [];
            foreach ($v['links'] as $link) {
                $names[$link['source']] = true;
                $names[$link['target']] = true;
            }
            $nodes = array_map(fn ($n): array => ['name' => $n], array_keys($names));
        }

        $option = [
            'tooltip' => ['trigger' => 'item', 'triggerOn' => 'mousemove'],
            'series' => [[
                'type' => 'sankey',
                'data' => $nodes,
                'links' => array_map(fn ($l): array => ['source' => $l['source'], 'target' => $l['target'], 'value' => $l['value']], $v['links']),
                'emphasis' => ['focus' => 'adjacency'],
                'lineStyle' => ['color' => 'gradient', 'curveness' => 0.5],
                'label' => ['fontSize' => 11],
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

            'title' => $schema->string()->description('Optional chart title.'),

            'summary' => $schema->string()
                ->description('One sentence describing what the diagram shows.')
                ->required(),
        ];
    }
}
