<?php

namespace App\Mcp;

use Domain\Integration\Mcp\Contracts\BuiltinToolDefinitions;
use Domain\Integration\Mcp\Data\McpToolDefinitionData;
use Illuminate\Contracts\Container\Container;
use Laravel\Mcp\Server\Tool;
use ReflectionClass;

/**
 * Reads tool definitions straight from a code-defined Laravel MCP server: its
 * `$tools` list, each tool rendered with `Tool::toArray()` — the same shape a
 * `tools/list` response carries, so it parses into the same DTO.
 */
final readonly class CodeDefinedToolDefinitions implements BuiltinToolDefinitions
{
    public function __construct(
        private Container $container,
    ) {}

    public function forServer(string $serverClass): array
    {
        $toolClasses = (array) ((new ReflectionClass($serverClass))->getDefaultProperties()['tools'] ?? []);

        return McpToolDefinitionData::listFrom(array_map(
            fn (string $toolClass): array => $this->tool($toolClass)->toArray(),
            $toolClasses,
        ));
    }

    /**
     * @param  class-string  $toolClass
     */
    private function tool(string $toolClass): Tool
    {
        /** @var Tool $tool */
        $tool = $this->container->make($toolClass);

        return $tool;
    }
}
