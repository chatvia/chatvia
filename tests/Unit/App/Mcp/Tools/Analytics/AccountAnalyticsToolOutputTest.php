<?php

declare(strict_types=1);

use App\Mcp\Servers\AccountAnalyticsServer;
use App\Mcp\Tools\Analytics\GetAccountOverviewTool;
use App\Mcp\Tools\Analytics\GetActivityPatternsTool;
use App\Mcp\Tools\Analytics\GetAgentBreakdownTool;
use App\Mcp\Tools\Analytics\GetAiUsageTool;
use App\Mcp\Tools\Analytics\GetChannelBreakdownTool;
use App\Mcp\Tools\Analytics\GetConversationStatsTool;
use App\Mcp\Tools\Analytics\GetGeographyStatsTool;
use App\Mcp\Tools\Analytics\GetHandoffStatsTool;
use App\Mcp\Tools\Analytics\GetLeadStatsTool;
use App\Mcp\Tools\Analytics\GetMessageStatsTool;
use App\Mcp\Tools\Analytics\GetResponseTimeStatsTool;
use App\Mcp\Tools\Analytics\GetSatisfactionStatsTool;
use App\Mcp\Tools\Analytics\GetTokenUsageTool;
use App\Mcp\Tools\Analytics\GetTopTopicsTool;
use App\Mcp\Tools\Analytics\GetTopUsersTool;
use Domain\Agent\Models\Agent;
use Illuminate\Support\Str;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use Tests\Support\Mcp\AnalyticsWorkspace;
use Tests\Support\Mcp\InternalMcp;

/**
 * Every Account-Analytics tool answers from SQL over the analytics rollups and
 * the live chat tables. These snapshots pin each tool's exact text + structured
 * result over one fixed, fully seeded workspace (AnalyticsWorkspace), so moving
 * the queries can't change a number.
 */

/**
 * The tool's JSON-RPC result as pretty JSON, with the run-specific agent ids
 * replaced by stable names.
 *
 * @param  array<string, Agent>  $agents
 */
function aatResult(TestResponse $response, array $agents): string
{
    $json = InternalMcp::resultJson($response);

    foreach ($agents as $name => $agent) {
        $json = Str::replace('"agent_id": '.$agent->id.',', '"agent_id": "@'.$name.'",', $json);
    }

    return $json;
}

dataset('analytics tool calls', [
    'overview, this month' => [GetAccountOverviewTool::class, []],
    'overview, all time' => [GetAccountOverviewTool::class, ['period' => 'all_time']],
    'overview, one agent' => [GetAccountOverviewTool::class, ['agent' => '@alpha']],
    'overview, unknown agent' => [GetAccountOverviewTool::class, ['agent' => 'no-such-agent']],
    'overview, invalid period' => [GetAccountOverviewTool::class, ['period' => 'yesterday']],
    'message stats, this month' => [GetMessageStatsTool::class, []],
    'message stats, one agent' => [GetMessageStatsTool::class, ['agent' => '@beta']],
    'message stats, last 7 days' => [GetMessageStatsTool::class, ['period' => 'last_7_days']],
    'conversation stats, this month' => [GetConversationStatsTool::class, []],
    'conversation stats, one agent' => [GetConversationStatsTool::class, ['agent' => '@alpha']],
    'conversation stats, empty window' => [GetConversationStatsTool::class, ['start_date' => '2025-01-01', 'end_date' => '2025-01-02']],
    'top users, this month' => [GetTopUsersTool::class, []],
    'top users, limit 1' => [GetTopUsersTool::class, ['limit' => 1]],
    'channel breakdown, this month' => [GetChannelBreakdownTool::class, []],
    'channel breakdown, one agent' => [GetChannelBreakdownTool::class, ['agent' => '@beta']],
    'agent breakdown, this month' => [GetAgentBreakdownTool::class, []],
    'agent breakdown, last month' => [GetAgentBreakdownTool::class, ['period' => 'last_month']],
    'geography, this month' => [GetGeographyStatsTool::class, []],
    'geography, limit 1' => [GetGeographyStatsTool::class, ['limit' => 1]],
    'activity patterns, this month' => [GetActivityPatternsTool::class, []],
    'activity patterns, one day' => [GetActivityPatternsTool::class, ['start_date' => '2026-06-14', 'end_date' => '2026-06-14']],
    'satisfaction, this month' => [GetSatisfactionStatsTool::class, []],
    'satisfaction, one agent' => [GetSatisfactionStatsTool::class, ['agent' => '@alpha']],
    'response time, this month' => [GetResponseTimeStatsTool::class, []],
    'response time, unknown agent' => [GetResponseTimeStatsTool::class, ['agent' => 'no-such-agent']],
    'response time, one agent' => [GetResponseTimeStatsTool::class, ['agent' => '@beta']],
    'top topics, this month' => [GetTopTopicsTool::class, []],
    'top topics, limit 1' => [GetTopTopicsTool::class, ['limit' => 1]],
    'lead stats, this month' => [GetLeadStatsTool::class, []],
    'lead stats, all time' => [GetLeadStatsTool::class, ['period' => 'all_time']],
    'handoff stats, this month' => [GetHandoffStatsTool::class, []],
    'token usage, this month' => [GetTokenUsageTool::class, []],
    'token usage, one agent' => [GetTokenUsageTool::class, ['agent' => '@alpha']],
    'token usage, empty window' => [GetTokenUsageTool::class, ['period' => 'today']],
    'ai usage, this month' => [GetAiUsageTool::class, []],
    'ai usage, limit 1' => [GetAiUsageTool::class, ['limit' => 1]],
]);

it('returns a stable result for each analytics tool', function (string $tool, array $arguments): void {
    $seeded = AnalyticsWorkspace::seed();
    $agents = ['alpha' => $seeded['alpha'], 'beta' => $seeded['beta']];

    if (isset($arguments['agent']) && Str::startsWith($arguments['agent'], '@')) {
        $arguments['agent'] = $agents[Str::after($arguments['agent'], '@')]->external_id;
    }

    InternalMcp::actAsWorkspace($seeded['workspace']);

    expect(aatResult(AccountAnalyticsServer::tool($tool, $arguments), $agents))->toMatchSnapshot();
})->with('analytics tool calls');

it('refuses every analytics tool without a workspace identity', function (string $tool): void {
    InternalMcp::actAsWorkspace(null);

    AccountAnalyticsServer::tool($tool)->assertHasErrors(['No workspace context for this analytics request.']);
})->with([
    GetAccountOverviewTool::class,
    GetActivityPatternsTool::class,
    GetAgentBreakdownTool::class,
    GetAiUsageTool::class,
    GetChannelBreakdownTool::class,
    GetConversationStatsTool::class,
    GetGeographyStatsTool::class,
    GetHandoffStatsTool::class,
    GetLeadStatsTool::class,
    GetMessageStatsTool::class,
    GetResponseTimeStatsTool::class,
    GetSatisfactionStatsTool::class,
    GetTokenUsageTool::class,
    GetTopTopicsTool::class,
    GetTopUsersTool::class,
]);

it('reports zeros, not the whole account, for an agent the workspace does not own', function (): void {
    $seeded = AnalyticsWorkspace::seed();
    $foreign = Agent::factory()->create();
    InternalMcp::actAsWorkspace($seeded['workspace']);

    AccountAnalyticsServer::tool(GetMessageStatsTool::class, ['agent' => $foreign->external_id])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('total_messages', 0)
            ->where('previous_total', 0)
            ->etc());
});
