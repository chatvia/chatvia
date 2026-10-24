<?php

namespace Domain\Analytics\Services\Chat;

use Illuminate\Support\Str;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Tool;
use ReflectionClass;

/**
 * Builds the fixed toolset for the owner analytics chat: every tool of the
 * built-in servers in config('mcp.builtin') — the internal account-analytics
 * server plus the interactive charts server. The tool definitions are read
 * straight from the MCP tool classes (name + description + input schema via
 * {@see Tool::toArray()}), so they never drift from the servers themselves,
 * and a name→endpoint map tells the dispatcher which internal `/mcp/...`
 * route to call for each tool.
 *
 * The servers are named by config rather than imported because they are HTTP
 * adapters in the App layer, which the Domain layer must not depend on.
 *
 * Nothing here touches the `mcp_servers` table or the per-agent pivot — the
 * toolset is purely in-memory, which is why the analytics server stays out of
 * the customer-facing connectors catalogue.
 */
class AnalyticsToolset
{
    /**
     * @return array{definitions: array<int, array<string, mixed>>, routes: array<string, string>}
     */
    public function build(): array
    {
        $base = Str::rtrim((string) config('app.url'), '/');

        $definitions = [];
        $routes = [];

        foreach ((array) config('mcp.builtin', []) as $entry) {
            $url = $base.'/mcp/'.$entry['slug'];

            foreach ($this->toolClasses($entry['class']) as $toolClass) {
                /** @var Tool $tool */
                $tool = app($toolClass);
                $schema = $tool->toArray();
                $name = $schema['name'];

                $definitions[] = [
                    'type' => 'function',
                    'function' => [
                        'name' => $name,
                        'description' => $schema['description'] ?? '',
                        'parameters' => $schema['inputSchema'] ?? ['type' => 'object', 'properties' => (object) []],
                    ],
                ];
                $routes[$name] = $url;
            }
        }

        return ['definitions' => $definitions, 'routes' => $routes];
    }

    /**
     * The tool class-strings a server registers, read from its protected
     * `$tools` default — no need to instantiate the Server (which requires a
     * transport argument).
     *
     * @param  class-string<Server>  $serverClass
     * @return array<int, class-string<Tool>>
     */
    private function toolClasses(string $serverClass): array
    {
        $tools = new ReflectionClass($serverClass)->getDefaultProperties()['tools'] ?? [];

        return is_array($tools) ? $tools : [];
    }
}
