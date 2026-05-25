<?php

namespace Tests\Unit\Services\Chat\Tooling;

use Domain\Agents\Models\Agent;
use Domain\Chat\Services\Tooling\ToolRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ToolRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('cache.default', 'array');
        Cache::store('array')->flush();
    }

    public function test_returns_empty_array_when_counter_is_zero_without_querying_db(): void
    {
        $agent = new Agent;
        $agent->id = 999;
        $agent->functions_count = 0;

        DB::enableQueryLog();
        $queriesBefore = count(DB::getQueryLog());

        $result = app(ToolRegistry::class)->for($agent);

        $queriesAfter = count(DB::getQueryLog());

        $this->assertSame([], $result);
        $this->assertSame($queriesBefore, $queriesAfter, 'No DB queries should run for zero-count agents.');
    }

    public function test_returns_empty_array_when_functions_count_attribute_is_missing(): void
    {
        $agent = new Agent;
        $agent->id = 999;

        $this->assertSame([], app(ToolRegistry::class)->for($agent));
    }

    public function test_caches_compiled_tools_payload_per_agent(): void
    {
        $agent = new Agent;
        $agent->id = 12345;
        $agent->functions_count = 1;

        Cache::shouldReceive('remember')
            ->once()
            ->with('agent:tools:12345', 3600, \Closure::class)
            ->andReturn([[
                'type' => 'function',
                'function' => ['name' => 'get_weather', 'description' => 'desc', 'parameters' => ['type' => 'object']],
            ]]);

        $result = app(ToolRegistry::class)->for($agent);

        $this->assertSame('get_weather', $result[0]['function']['name']);
    }

    public function test_forget_busts_the_per_agent_cache(): void
    {
        Cache::put('agent:tools:42', ['x'], 60);

        app(ToolRegistry::class)->forget(42);

        $this->assertNull(Cache::get('agent:tools:42'));
    }
}
