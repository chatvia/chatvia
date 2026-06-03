<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\Tooling\Approval\ToolApprovalPolicy;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\McpTool;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Str;

function approvalAgent(): array
{
    $user = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($user);

    $agent = Agent::create([
        'user_id' => $user->id,
        'team_id' => $user->primaryTeam()->id,
        'name' => 'Approval Agent',
        'external_id' => Str::random(14),
        'sub_domain' => Str::lower(Str::random(12)),
        'is_public' => false,
        'source' => 'api',
    ]);

    return [$user, $agent];
}

function makeMcpTool(User $user, bool $serverApproval, bool $toolApproval): string
{
    $server = AgentMcpServer::create([
        'user_id' => $user->id,
        'name' => 'Test Server',
        'slug' => 'test',
        'url' => 'https://mcp.example.test',
        'transport' => 'streamable_http',
        'auth_type' => 'none',
        'requires_approval' => $serverApproval,
        'is_enabled' => true,
    ]);

    McpTool::create([
        'mcp_server_id' => $server->id,
        'name' => 'do_thing',
        'description' => 'Does a thing',
        'input_schema' => ['type' => 'object', 'properties' => []],
        'requires_approval' => $toolApproval,
        'is_enabled' => true,
        'fetched_at' => now(),
    ]);

    return 'mcp__test__do_thing';
}

function makeApprovalFunction(User $user, bool $requiresApproval): AgentFunction
{
    $function = new AgentFunction;
    $function->user_id = $user->id;
    $function->internal_name = 'write_thing';
    $function->description = 'Writes a thing';
    $function->parameters_schema = ['type' => 'object', 'properties' => []];
    $function->webhook_url = 'https://hooks.example.test/fn';
    $function->webhook_method = 'POST';
    $function->auth_type = 'none';
    $function->requires_approval = $requiresApproval;
    $function->is_enabled = true;
    $function->save();

    return $function;
}

it('gates an MCP tool only when both the server and the tool require approval', function (): void {
    [$user, $agent] = approvalAgent();
    $name = makeMcpTool($user, serverApproval: true, toolApproval: true);

    expect(app(ToolApprovalPolicy::class)->requiresApproval($agent, $name))->toBeTrue();
});

it('auto-runs an MCP tool when the connector master switch is off', function (): void {
    [$user, $agent] = approvalAgent();
    $name = makeMcpTool($user, serverApproval: false, toolApproval: true);

    expect(app(ToolApprovalPolicy::class)->requiresApproval($agent, $name))->toBeFalse();
});

it('auto-runs an MCP tool when the per-tool override is off', function (): void {
    [$user, $agent] = approvalAgent();
    $name = makeMcpTool($user, serverApproval: true, toolApproval: false);

    expect(app(ToolApprovalPolicy::class)->requiresApproval($agent, $name))->toBeFalse();
});

it('gates an unknown MCP tool under an approval-on server (stale catalog is treated as risky)', function (): void {
    [$user, $agent] = approvalAgent();
    AgentMcpServer::create([
        'user_id' => $user->id,
        'name' => 'Test Server',
        'slug' => 'test',
        'url' => 'https://mcp.example.test',
        'transport' => 'streamable_http',
        'auth_type' => 'none',
        'requires_approval' => true,
        'is_enabled' => true,
    ]);

    expect(app(ToolApprovalPolicy::class)->requiresApproval($agent, 'mcp__test__not_catalogued'))->toBeTrue();
});

it('gates a function based on its own requires_approval flag', function (): void {
    [$user, $agent] = approvalAgent();
    makeApprovalFunction($user, requiresApproval: true);

    expect(app(ToolApprovalPolicy::class)->requiresApproval($agent, 'write_thing'))->toBeTrue();
});

it('auto-runs a function whose requires_approval flag is off', function (): void {
    [$user, $agent] = approvalAgent();
    makeApprovalFunction($user, requiresApproval: false);

    expect(app(ToolApprovalPolicy::class)->requiresApproval($agent, 'write_thing'))->toBeFalse();
});

it('bypasses approval entirely when the global kill-switch is off', function (): void {
    config(['agent.tools.approval_enabled' => false]);

    [$user, $agent] = approvalAgent();
    $name = makeMcpTool($user, serverApproval: true, toolApproval: true);

    expect(app(ToolApprovalPolicy::class)->requiresApproval($agent, $name))->toBeFalse();
});
