<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\Tooling\Approval\ToolApprovalPolicy;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Mcp\Enums\McpToolPermission;
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

function makeMcpTool(User $user, McpToolPermission $permission): string
{
    $server = AgentMcpServer::create([
        'user_id' => $user->id,
        'name' => 'Test Server',
        'slug' => 'test',
        'url' => 'https://mcp.example.test',
        'transport' => 'streamable_http',
        'auth_type' => 'none',
        'is_enabled' => true,
    ]);

    McpTool::create([
        'mcp_server_id' => $server->id,
        'name' => 'do_thing',
        'description' => 'Does a thing',
        'input_schema' => ['type' => 'object', 'properties' => []],
        'permission' => $permission,
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

it('gates an MCP tool whose permission is Approval', function (): void {
    [$user, $agent] = approvalAgent();
    $name = makeMcpTool($user, McpToolPermission::Approval);

    expect(app(ToolApprovalPolicy::class)->requiresApproval($agent, $name))->toBeTrue();
});

it('auto-runs an MCP tool whose permission is Allow', function (): void {
    [$user, $agent] = approvalAgent();
    $name = makeMcpTool($user, McpToolPermission::Allow);

    expect(app(ToolApprovalPolicy::class)->requiresApproval($agent, $name))->toBeFalse();
});

it('gates an unknown MCP tool (stale catalog is treated as risky)', function (): void {
    [$user, $agent] = approvalAgent();
    AgentMcpServer::create([
        'user_id' => $user->id,
        'name' => 'Test Server',
        'slug' => 'test',
        'url' => 'https://mcp.example.test',
        'transport' => 'streamable_http',
        'auth_type' => 'none',
        'is_enabled' => true,
    ]);

    expect(app(ToolApprovalPolicy::class)->requiresApproval($agent, 'mcp__test__not_catalogued'))->toBeTrue();
});

it('runs a builtin capability tool directly, never gating it', function (): void {
    [$user, $agent] = approvalAgent();

    // A builtin server (slug 'forms') whose tool is seeded as Approval — the
    // first-party builtin carve-out must still auto-run it.
    $server = AgentMcpServer::create([
        'user_id' => $user->id,
        'category' => 'builtin',
        'connector_slug' => 'forms',
        'name' => 'Forms',
        'slug' => 'forms',
        'url' => config('app.url').'/mcp/forms',
        'auth_type' => 'none',
        'is_enabled' => true,
    ]);
    McpTool::create([
        'mcp_server_id' => $server->id,
        'name' => 'render_form',
        'description' => 'Render a form',
        'input_schema' => ['type' => 'object', 'properties' => []],
        'permission' => McpToolPermission::Approval,
        'fetched_at' => now(),
    ]);

    expect(app(ToolApprovalPolicy::class)->requiresApproval($agent, 'mcp__forms__render_form'))->toBeFalse();
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
    config(['chatvia.agent.tools.approval_enabled' => false]);

    [$user, $agent] = approvalAgent();
    $name = makeMcpTool($user, McpToolPermission::Approval);

    expect(app(ToolApprovalPolicy::class)->requiresApproval($agent, $name))->toBeFalse();
});
