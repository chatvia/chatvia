<?php

namespace Tests\Unit\Chat\Tooling;

use App\Embeddings\Contracts\EmbeddingsProviderInterface;
use Domain\Chat\Services\Tooling\CosineSimilarity;
use Domain\Chat\Services\Tooling\SemanticToolRouter;
use Domain\Chat\Services\Tooling\ToolEntry;
use Tests\TestCase;

class SemanticToolRouterTest extends TestCase
{
    /**
     * The router uses a fixed RELEVANCE_THRESHOLD (0.5), MIN_TOOLS floor (5),
     * and MAX_TOOLS ceiling (30). Build vectors that produce known cosines
     * against a fixed query so we can pin down each branch.
     */
    public function test_returns_only_tools_above_threshold_when_enough_pass(): void
    {
        $router = $this->makeRouter([1.0, 0.0]);

        $pool = [
            $this->entry('a', [1.0, 0.0]),  // cos = 1.0
            $this->entry('b', [0.9, 0.4]),  // cos ≈ 0.91
            $this->entry('c', [0.7, 0.7]),  // cos ≈ 0.71
            $this->entry('d', [0.6, 0.8]),  // cos = 0.6
            $this->entry('e', [0.5, 0.86]), // cos = 0.5 — boundary, included
            $this->entry('f', [0.4, 0.91]), // cos = 0.4 — below threshold
            $this->entry('g', [0.0, 1.0]),  // cos = 0.0
        ];

        $result = $router->route('whatever', $pool);

        // 5 entries score ≥ 0.5; the floor (MIN_TOOLS = 5) is satisfied
        // by passing alone, so no extras are pulled in.
        $this->assertCount(5, $result);
        $names = array_map(fn (ToolEntry $e): string => $e->name, $result);
        $this->assertSame(['a', 'b', 'c', 'd', 'e'], $names);
    }

    public function test_floor_pulls_top_k_when_too_few_pass_threshold(): void
    {
        $router = $this->makeRouter([1.0, 0.0]);

        // Only 2 entries pass 0.5, but MIN_TOOLS = 5 — top 5 should come back.
        $pool = [
            $this->entry('a', [1.0, 0.0]),   // 1.0
            $this->entry('b', [0.8, 0.6]),   // 0.8
            $this->entry('c', [0.4, 0.91]),  // 0.4
            $this->entry('d', [0.3, 0.95]),  // 0.3
            $this->entry('e', [0.2, 0.98]),  // 0.2
            $this->entry('f', [0.1, 0.99]),  // 0.1
            $this->entry('g', [0.0, 1.0]),   // 0.0
        ];

        $result = $router->route('whatever', $pool);

        $this->assertCount(5, $result);
        $names = array_map(fn (ToolEntry $e): string => $e->name, $result);
        $this->assertSame(['a', 'b', 'c', 'd', 'e'], $names);
    }

    public function test_ceiling_caps_at_max_tools_when_too_many_pass(): void
    {
        $router = $this->makeRouter([1.0, 0.0]);

        // 35 entries all scoring 1.0 → ceiling at 30.
        $pool = [];
        for ($i = 0; $i < 35; $i++) {
            $pool[] = $this->entry("tool-$i", [1.0, 0.0]);
        }

        $result = $router->route('whatever', $pool);

        $this->assertCount(30, $result);
    }

    public function test_falls_back_to_pool_when_embedding_returns_empty(): void
    {
        $router = $this->makeRouter([]); // empty query vector

        $pool = [
            $this->entry('a', [1.0, 0.0]),
            $this->entry('b', [0.0, 1.0]),
        ];

        $result = $router->route('whatever', $pool);

        $this->assertSame($pool, $result);
    }

    public function test_excludes_unembedded_tools_from_scoring(): void
    {
        $router = $this->makeRouter([1.0, 0.0]);

        // 4 tools without embeddings, 1 with — floor pulls top 5 but
        // unembedded ones sort to the bottom (-INF score). Only the
        // one with an embedding scores well.
        $pool = [
            $this->entry('embedded', [1.0, 0.0]),
            $this->entry('a', null),
            $this->entry('b', null),
            $this->entry('c', null),
            $this->entry('d', null),
        ];

        $result = $router->route('whatever', $pool);

        // First slot should always be the embedded tool; the floor fills
        // the rest from -INF sorted tail (still returned, but never
        // outranks an embedded one).
        $this->assertSame('embedded', $result[0]->name);
    }

    private function makeRouter(array $queryVector): SemanticToolRouter
    {
        $embeddings = $this->createMock(EmbeddingsProviderInterface::class);
        $embeddings->method('embedQuery')->willReturn($queryVector);

        return new SemanticToolRouter($embeddings, new CosineSimilarity());
    }

    private function entry(string $name, ?array $embedding): ToolEntry
    {
        return new ToolEntry(
            kind: 'mcp',
            sourceId: 1,
            name: $name,
            description: '',
            parameters: [],
            embedding: $embedding,
        );
    }
}
