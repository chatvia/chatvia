<?php

namespace Tests\Unit\Services\Chat\Tooling;

use Domain\Agents\Models\Agent;
use Domain\Chat\Services\Tooling\Registry\ToolEntry;
use Domain\Chat\Services\Tooling\Registry\ToolRegistry;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ToolRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('cache.default', 'array');
        Cache::store('array')->flush();
    }

    public function test_caches_compiled_pool_per_agent(): void
    {
        $agent = new Agent;
        $agent->id = 12345;

        $entry = new ToolEntry(
            kind: 'mcp',
            sourceId: 1,
            name: 'get_weather',
            description: 'desc',
            parameters: ['type' => 'object'],
            embedding: null,
        );

        Cache::shouldReceive('has')
            ->once()
            ->with('agent:tools:12345')
            ->andReturn(false);

        Cache::shouldReceive('remember')
            ->once()
            ->with('agent:tools:12345', 3600, \Closure::class)
            ->andReturn([$entry]);

        $result = app(ToolRegistry::class)->for($agent);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(ToolEntry::class, $result[0]);
        $this->assertSame('get_weather', $result[0]->name);
    }

    public function test_forget_busts_the_per_agent_cache(): void
    {
        Cache::put('agent:tools:42', ['x'], 60);

        app(ToolRegistry::class)->forget(42);

        $this->assertNull(Cache::get('agent:tools:42'));
    }
}
