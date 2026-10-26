<?php

declare(strict_types=1);

use Domain\Integration\Mcp\Services\InternalMcpSignature;
use Illuminate\Support\Str;

/**
 * The built-in MCP servers are a contract with the LLM: the server name and
 * instructions, and every tool's name, description, input schema and
 * annotations. These snapshots pin what each internal /mcp/* route advertises
 * (initialize + every tools/list page, through the real route and middleware),
 * so moving or refactoring a server or tool can't silently change what the
 * model sees.
 */

/**
 * POST one signed JSON-RPC call to an internal MCP route and return its body.
 *
 * @param  array<string, mixed>  $params
 * @return array<string, mixed>
 */
function contractRpc(string $path, string $method, array $params = []): array
{
    $payload = ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params === [] ? (object) [] : $params];
    $headers = InternalMcpSignature::headers('POST', $path, (string) json_encode($payload), []);

    return test()->withHeaders($headers)->postJson($path, $payload)->assertOk()->json();
}

/**
 * Every tool the route advertises, following tools/list cursors.
 *
 * @return list<array<string, mixed>>
 */
function contractToolList(string $path): array
{
    $tools = [];
    $cursor = null;

    do {
        $result = contractRpc($path, 'tools/list', $cursor === null ? [] : ['cursor' => $cursor])['result'];
        $tools = [...$tools, ...$result['tools']];
        $cursor = $result['nextCursor'] ?? null;
    } while ($cursor !== null);

    return $tools;
}

/**
 * The route's answer to the MCP handshake.
 *
 * @return array<string, mixed>
 */
function contractInitialize(string $path): array
{
    return contractRpc($path, 'initialize', [
        'protocolVersion' => '2025-06-18',
        'capabilities' => (object) [],
        'clientInfo' => ['name' => 'contract-test', 'version' => '1.0.0'],
    ])['result'];
}

function contractJson(mixed $value): string
{
    return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

dataset('builtin mcp routes', [
    'weather' => ['/mcp/weather'],
    'currency' => ['/mcp/currency'],
    'charts' => ['/mcp/charts'],
    'forms' => ['/mcp/forms'],
    'calendar' => ['/mcp/calendar'],
    'image-generation' => ['/mcp/image-generation'],
    'audio-generation' => ['/mcp/audio-generation'],
    'perplexity' => ['/mcp/perplexity'],
    'account-analytics' => ['/mcp/account-analytics'],
]);

it('keeps the server identity and instructions stable', function (string $path): void {
    $result = contractInitialize($path);

    expect(contractJson([
        'serverInfo' => $result['serverInfo'],
        'instructions' => $result['instructions'] ?? null,
    ]))->toMatchSnapshot();
})->with('builtin mcp routes');

it('keeps every tool definition stable', function (string $path): void {
    expect(contractJson(contractToolList($path)))->toMatchSnapshot();
})->with('builtin mcp routes');

it('names only tools the server has in its instructions', function (string $path): void {
    $instructions = (string) (contractInitialize($path)['instructions'] ?? '');

    // Tool names are kebab-case and end in -tool (render-bar-chart-tool); a
    // name the model reads here must be one it can call.
    $named = Str::matchAll('/\b[a-z]+(?:-[a-z]+)*-tool\b/', $instructions);
    $toolNames = array_column(contractToolList($path), 'name');

    expect($named->unique()->diff($toolNames)->values()->all())->toBe([]);
})->with('builtin mcp routes');

it('lists every charts tool by its name in the charts instructions', function (): void {
    $instructions = (string) contractInitialize('/mcp/charts')['instructions'];

    $listed = array_map(fn (string $name): string => "- {$name} ", array_column(contractToolList('/mcp/charts'), 'name'));

    expect($instructions)->toContain(...$listed);
});
