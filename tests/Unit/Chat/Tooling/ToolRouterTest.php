<?php

namespace Tests\Unit\Chat\Tooling;

use Domain\Chat\Services\Tooling\ToolRouter;
use PHPUnit\Framework\TestCase;

class ToolRouterTest extends TestCase
{
    private ToolRouter $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new ToolRouter;
    }

    public function test_recognises_prefixed_mcp_tool_names(): void
    {
        $this->assertTrue($this->router->isMcp('mcp__stripe__create_customer'));
        $this->assertTrue($this->router->isMcp('mcp__github__list_issues'));
        $this->assertTrue($this->router->isMcp('mcp__test__get_weather'));
    }

    public function test_rejects_anything_without_double_underscore_split(): void
    {
        $this->assertFalse($this->router->isMcp('get_weather'));
        $this->assertFalse($this->router->isMcp('mcp_stripe_x'));
        $this->assertFalse($this->router->isMcp('mcp__only_slug'));
        $this->assertFalse($this->router->isMcp(''));
    }
}
