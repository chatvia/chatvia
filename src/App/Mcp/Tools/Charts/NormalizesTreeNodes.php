<?php

namespace App\Mcp\Tools\Charts;

/**
 * Cleans the free-form `{name, value?, children?}` tree that hierarchical
 * charts (treemap, sunburst) accept: nodes without a name are dropped, the
 * name becomes a string, a non-numeric value is left out (ECharts then sums
 * the children), and children are cleaned the same way at every depth.
 */
trait NormalizesTreeNodes
{
    /**
     * @param  array<int|string, mixed>  $nodes
     * @return list<array<string, mixed>>
     */
    protected function normalizeTree(array $nodes): array
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
                $entry['children'] = $this->normalizeTree($node['children']);
            }
            $out[] = $entry;
        }

        return $out;
    }
}
