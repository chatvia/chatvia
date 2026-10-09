<?php

use App\Identity\Workspace\Enums\UserType;
use App\Identity\Workspace\Models\User;
use App\Mcp\McpCallerContext;
use App\Mcp\McpCallerResolver;
use App\Mcp\Servers\AccountAnalyticsServer;
use App\Mcp\Tools\Analytics\GetAccountOverviewTool;
use App\Mcp\Tools\Analytics\GetActivityPatternsTool;
use App\Mcp\Tools\Analytics\GetAgentBreakdownTool;
use App\Mcp\Tools\Analytics\GetAiUsageTool;
use App\Mcp\Tools\Analytics\GetBillingCycleUsageTool;
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
use Carbon\CarbonImmutable;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;
use Domain\Form\Enums\FormSubmissionStatus;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Illuminate\Database\Connection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;

/**
 * Every Account-Analytics tool answers from SQL over the analytics rollups and
 * the live chat tables. These snapshots pin each tool's exact text + structured
 * result over one fixed, fully seeded workspace (two agents, a billing cycle, a
 * second workspace as noise), so moving the queries can't change a number.
 */
pest()->beforeEach(function (): void {
    if (! Schema::hasColumn('form_definitions', 'embedding')) {
        $this->markTestSkipped('schema out of date — migrate the testing DB.');
    }
});

/** @return array{workspace: User, alpha: Agent, beta: Agent} */
function seedAnalyticsWorkspace(): array
{
    test()->travelTo(CarbonImmutable::create(2026, 6, 15, 12, 30, 0));

    $workspace = User::factory()->create(['type' => UserType::Workspace]);
    $alpha = Agent::factory()->create(['user_id' => $workspace->id, 'name' => 'Alpha']);
    $beta = Agent::factory()->create(['user_id' => $workspace->id, 'name' => 'Beta']);

    $other = User::factory()->create(['type' => UserType::Workspace]);
    $noise = Agent::factory()->create(['user_id' => $other->id, 'name' => 'Noise']);

    seedAnalyticsBilling($workspace);
    seedAnalyticsEvents($workspace, $alpha, $beta, $other, $noise);
    seedAnalyticsRollups($workspace, $alpha, $beta, $other, $noise);
    seedAnalyticsChats($alpha, $beta, $noise);

    return ['workspace' => $workspace, 'alpha' => $alpha, 'beta' => $beta];
}

function seedAnalyticsBilling(User $workspace): void
{
    $previous = $workspace->credits()->create([
        'messages' => 800,
        'started_at' => '2026-05-01 00:00:00',
        'ends_at' => '2026-05-31 23:59:59',
    ]);
    $previous->usage()->create(['messages' => 700, 'messages_exceeded' => 0, 'tokens' => 5000]);

    $current = $workspace->credits()->create([
        'messages' => 1000,
        'started_at' => '2026-06-01 00:00:00',
        'ends_at' => '2026-07-01 00:00:00',
    ]);
    $current->usage()->create(['messages' => 420, 'messages_exceeded' => 15, 'tokens' => 98765]);

    $workspace->addons()->create(['type' => 'extra_message_credits', 'amount' => 500, 'stripe_id' => 'cs_test_open', 'started_at' => '2026-06-02 00:00:00', 'ends_at' => null]);
    $workspace->addons()->create(['type' => 'extra_message_credits', 'amount' => 300, 'stripe_id' => 'cs_test_late', 'started_at' => '2026-06-03 00:00:00', 'ends_at' => '2026-06-20 00:00:00']);
    $workspace->addons()->create(['type' => 'extra_message_credits', 'amount' => 900, 'stripe_id' => 'cs_test_gone', 'started_at' => '2026-05-01 00:00:00', 'ends_at' => '2026-05-10 00:00:00']);
}

function seedAnalyticsEvents(User $workspace, Agent $alpha, Agent $beta, User $other, Agent $noise): void
{
    $chatterOne = User::factory()->create(['type' => UserType::Chatter, 'name' => 'Ada Lovelace', 'email' => 'ada@example.test']);
    $chatterTwo = User::factory()->create(['type' => UserType::Chatter, 'name' => 'Grace Hopper', 'email' => 'grace@example.test']);

    $conversations = [
        'one' => '00000000-0000-4000-8000-000000000001',
        'two' => '00000000-0000-4000-8000-000000000002',
        'three' => '00000000-0000-4000-8000-000000000003',
        'old' => '00000000-0000-4000-8000-000000000004',
        'noise' => '00000000-0000-4000-8000-000000000005',
    ];

    $event = fn (int $workspaceId, Agent $agent, string $type, string $conversation, string $at, array $extra = []): array => [
        'agent_id' => $agent->id,
        'workspace_id' => $workspaceId,
        'chatter_user_id' => null,
        'event_type' => $type,
        'role' => null,
        'feedback' => null,
        'response_time_ms' => null,
        'prompt_tokens' => null,
        'completion_tokens' => null,
        'total_tokens' => null,
        'conversation_external_id' => $conversations[$conversation],
        'message_external_id' => (string) Str::uuid(),
        'session_external_id' => '00000000-0000-4000-9000-000000000001',
        'occurred_at' => $at,
        'inserted_at' => $at,
        ...$extra,
    ];

    $user = fn (?User $chatter): array => ['role' => 'user', 'chatter_user_id' => $chatter?->id];
    $reply = fn (int $ms, int $prompt, int $completion): array => [
        'role' => 'assistant',
        'response_time_ms' => $ms,
        'prompt_tokens' => $prompt,
        'completion_tokens' => $completion,
        'total_tokens' => $prompt + $completion,
    ];

    seededAnalyticsDb()->table('analytics_events')->insert([
        $event($workspace->id, $alpha, 'message_sent', 'one', '2026-06-10 09:15:00', $user($chatterOne)),
        $event($workspace->id, $alpha, 'message_sent', 'one', '2026-06-10 09:15:02', $reply(1200, 100, 50)),
        $event($workspace->id, $alpha, 'message_sent', 'one', '2026-06-10 09:20:00', $user($chatterOne)),
        $event($workspace->id, $alpha, 'message_sent', 'one', '2026-06-10 09:20:01', $reply(900, 80, 40)),
        $event($workspace->id, $alpha, 'message_sent', 'two', '2026-06-12 14:00:00', $user($chatterTwo)),
        $event($workspace->id, $alpha, 'message_sent', 'two', '2026-06-12 14:00:05', $reply(800, 120, 80)),
        $event($workspace->id, $beta, 'message_sent', 'three', '2026-06-14 20:10:00', $user(null)),
        $event($workspace->id, $beta, 'message_sent', 'three', '2026-06-14 20:10:03', $reply(3000, 60, 30)),
        $event($workspace->id, $beta, 'message_sent', 'old', '2026-05-20 10:00:00', $user($chatterOne)),
        $event($workspace->id, $beta, 'message_sent', 'old', '2026-05-20 10:00:02', $reply(1500, 10, 10)),
        $event($workspace->id, $alpha, 'feedback_given', 'one', '2026-06-10 09:16:00', ['feedback' => 'positive']),
        $event($workspace->id, $beta, 'feedback_given', 'three', '2026-06-14 20:11:00', ['feedback' => 'negative']),
        $event($workspace->id, $beta, 'feedback_given', 'three', '2026-06-14 20:12:00', ['feedback' => 'positive']),
        $event($other->id, $noise, 'message_sent', 'noise', '2026-06-11 11:00:00', $user($chatterTwo)),
        $event($other->id, $noise, 'message_sent', 'noise', '2026-06-11 11:00:01', $reply(99, 999, 999)),
        $event($other->id, $noise, 'feedback_given', 'noise', '2026-06-11 11:01:00', ['feedback' => 'negative']),
    ]);

    seededAnalyticsDb()->table('analytics_conversation_metrics')->insert([
        ['conversation_external_id' => $conversations['one'], 'agent_id' => $alpha->id, 'workspace_id' => $workspace->id, 'started_at' => '2026-06-10 09:15:00', 'ended_at' => '2026-06-10 09:20:01', 'message_count' => 4, 'duration_seconds' => 301],
        ['conversation_external_id' => $conversations['two'], 'agent_id' => $alpha->id, 'workspace_id' => $workspace->id, 'started_at' => '2026-06-12 14:00:00', 'ended_at' => '2026-06-12 14:00:05', 'message_count' => 2, 'duration_seconds' => 0],
        ['conversation_external_id' => $conversations['three'], 'agent_id' => $beta->id, 'workspace_id' => $workspace->id, 'started_at' => '2026-06-14 20:10:00', 'ended_at' => '2026-06-14 20:10:03', 'message_count' => 2, 'duration_seconds' => 3],
        ['conversation_external_id' => $conversations['old'], 'agent_id' => $beta->id, 'workspace_id' => $workspace->id, 'started_at' => '2026-05-20 10:00:00', 'ended_at' => '2026-05-20 10:00:02', 'message_count' => 2, 'duration_seconds' => 2],
        ['conversation_external_id' => $conversations['noise'], 'agent_id' => $noise->id, 'workspace_id' => $other->id, 'started_at' => '2026-06-11 11:00:00', 'ended_at' => '2026-06-11 11:00:01', 'message_count' => 50, 'duration_seconds' => 999],
    ]);

    seededAnalyticsDb()->table('analytics_session_firsts')->insert([
        ['agent_id' => $alpha->id, 'workspace_id' => $workspace->id, 'session_external_id' => '00000000-0000-4000-9000-000000000011', 'first_seen_at' => '2026-06-10 09:14:00'],
        ['agent_id' => $alpha->id, 'workspace_id' => $workspace->id, 'session_external_id' => '00000000-0000-4000-9000-000000000012', 'first_seen_at' => '2026-06-12 13:59:00'],
        ['agent_id' => $beta->id, 'workspace_id' => $workspace->id, 'session_external_id' => '00000000-0000-4000-9000-000000000013', 'first_seen_at' => '2026-06-14 20:09:00'],
        ['agent_id' => $beta->id, 'workspace_id' => $workspace->id, 'session_external_id' => '00000000-0000-4000-9000-000000000014', 'first_seen_at' => '2026-05-20 09:59:00'],
        ['agent_id' => $noise->id, 'workspace_id' => $other->id, 'session_external_id' => '00000000-0000-4000-9000-000000000015', 'first_seen_at' => '2026-06-11 10:59:00'],
    ]);
}

function seedAnalyticsRollups(User $workspace, Agent $alpha, Agent $beta, User $other, Agent $noise): void
{
    $hour = fn (int $workspaceId, Agent $agent, string $bucket, string $country, int $conversations, int $user, int $assistant): array => [
        'agent_id' => $agent->id,
        'workspace_id' => $workspaceId,
        'bucket_hour' => $bucket,
        'country_code' => $country,
        'llm_external_name' => 'qwen3',
        'conversations_started' => $conversations,
        'user_messages' => $user,
        'assistant_messages' => $assistant,
        'total_tokens' => 0,
        'response_ms_sum' => 0,
        'response_ms_count' => 0,
    ];

    seededAnalyticsDb()->table('analytics_agent_hourly_rollups')->insert([
        $hour($workspace->id, $alpha, '2026-06-10 09:00:00', 'NO', 1, 2, 2),
        $hour($workspace->id, $alpha, '2026-06-12 14:00:00', 'DE', 2, 1, 1),
        $hour($workspace->id, $beta, '2026-06-14 20:00:00', 'US', 2, 1, 1),
        $hour($workspace->id, $beta, '2026-06-14 21:00:00', '--', 3, 1, 1),
        $hour($workspace->id, $beta, '2026-06-08 09:00:00', 'DE', 2, 4, 4),
        $hour($workspace->id, $beta, '2026-05-20 10:00:00', 'NO', 1, 1, 1),
        $hour($other->id, $noise, '2026-06-11 11:00:00', 'US', 40, 40, 40),
    ]);

    $day = fn (int $workspaceId, Agent $agent, string $bucket, array $columns): array => [
        'agent_id' => $agent->id,
        'workspace_id' => $workspaceId,
        'bucket_day' => $bucket,
        ...$columns,
    ];

    seededAnalyticsDb()->table('analytics_agent_sentiment_daily')->insert([
        $day($workspace->id, $alpha, '2026-06-10', ['sentiment' => 'positive', 'message_count' => 3]),
        $day($workspace->id, $alpha, '2026-06-12', ['sentiment' => 'neutral', 'message_count' => 2]),
        $day($workspace->id, $beta, '2026-06-14', ['sentiment' => 'negative', 'message_count' => 1]),
        $day($other->id, $noise, '2026-06-11', ['sentiment' => 'negative', 'message_count' => 50]),
    ]);

    $pricing = DB::table('topics')->insertGetId(['topic' => 'Pricing', 'description' => 'Plans and prices']);
    $shipping = DB::table('topics')->insertGetId(['topic' => 'Shipping', 'description' => 'Delivery questions']);

    seededAnalyticsDb()->table('analytics_agent_topic_daily')->insert([
        $day($workspace->id, $alpha, '2026-06-10', ['topic_id' => $pricing, 'conversation_count' => 2]),
        $day($workspace->id, $alpha, '2026-06-12', ['topic_id' => $pricing, 'conversation_count' => 1]),
        $day($workspace->id, $beta, '2026-06-14', ['topic_id' => $shipping, 'conversation_count' => 1]),
        $day($other->id, $noise, '2026-06-11', ['topic_id' => $shipping, 'conversation_count' => 30]),
    ]);

    seededAnalyticsDb()->table('analytics_agent_function_daily')->insert([
        $day($workspace->id, $alpha, '2026-06-10', ['function_id' => 11, 'function_name' => 'lookup_order', 'invocation_count' => 5]),
        $day($workspace->id, $beta, '2026-06-14', ['function_id' => 12, 'function_name' => 'create_ticket', 'invocation_count' => 2]),
        $day($other->id, $noise, '2026-06-11', ['function_id' => 13, 'function_name' => 'noise_fn', 'invocation_count' => 90]),
    ]);

    seededAnalyticsDb()->table('analytics_agent_mcp_tool_daily')->insert([
        $day($workspace->id, $alpha, '2026-06-10', ['server_slug' => 'charts', 'tool_name' => 'render-bar-chart-tool', 'server_name' => 'Charts', 'invocation_count' => 4]),
        $day($workspace->id, $beta, '2026-06-14', ['server_slug' => 'weather', 'tool_name' => 'current-weather-tool', 'server_name' => 'Weather', 'invocation_count' => 1]),
    ]);

    seededAnalyticsDb()->table('analytics_agent_source_daily')->insert([
        $day($workspace->id, $alpha, '2026-06-10', ['sourceable_type' => 'source_file', 'sourceable_id' => 1, 'source_name' => 'Handbook.pdf', 'retrieval_count' => 7]),
        $day($workspace->id, $beta, '2026-06-14', ['sourceable_type' => 'source_website', 'sourceable_id' => 2, 'source_name' => null, 'retrieval_count' => 3]),
    ]);
}

function seedAnalyticsChats(Agent $alpha, Agent $beta, Agent $noise): void
{
    $session = fn (Agent $agent, string $device, string $browser, string $os, string $at): Session => Session::factory()->create([
        'agent_id' => $agent->id,
        'device_type' => $device,
        'browser' => $browser,
        'os' => $os,
        'created_at' => $at,
    ]);

    $desktop = $session($alpha, 'desktop', 'Chrome', 'macOS', '2026-06-10 09:14:00');
    $phone = $session($alpha, 'mobile', 'Safari', 'iOS', '2026-06-12 13:59:00');
    $secondPhone = $session($alpha, 'mobile', 'Safari', 'iOS', '2026-06-13 08:00:00');
    $android = $session($beta, 'mobile', 'Chrome', 'Android', '2026-06-14 20:09:00');
    $secondAndroid = $session($beta, 'mobile', 'Chrome', 'Android', '2026-06-14 22:00:00');
    $thirdAndroid = $session($beta, 'mobile', 'Chrome', 'Android', '2026-06-09 07:00:00');
    $noiseSession = $session($noise, 'tablet', 'Edge', 'Windows', '2026-06-11 10:59:00');

    $conversation = fn (Session $session, ConversationSource $source, string $at, array $extra = []): Conversation => Conversation::factory()->create([
        'chat_session_id' => $session->id,
        'agent_id' => $session->agent_id,
        'source' => $source,
        'created_at' => $at,
        ...$extra,
    ]);

    $widget = $conversation($desktop, ConversationSource::Widget, '2026-06-10 09:15:00');
    $hosted = $conversation($phone, ConversationSource::HostedChat, '2026-06-12 14:00:00', ['handoff_at' => '2026-06-12 14:01:00', 'handoff_status' => 'human']);
    $telegram = $conversation($android, ConversationSource::Telegram, '2026-06-14 20:10:00', ['handoff_at' => '2026-06-14 20:11:00', 'handoff_status' => 'pending']);
    $conversation($secondPhone, ConversationSource::Widget, '2026-06-13 08:01:00');
    $conversation($secondAndroid, ConversationSource::Widget, '2026-06-14 22:01:00');
    $conversation($thirdAndroid, ConversationSource::Telegram, '2026-06-09 07:01:00', ['handoff_status' => 'ai']);
    $conversation($android, ConversationSource::Widget, '2026-06-14 20:20:00', ['deleted_at' => '2026-06-14 21:00:00']);
    $conversation($android, ConversationSource::Widget, '2026-05-21 08:00:00', ['handoff_status' => 'pending']);
    $noiseConversation = $conversation($noiseSession, ConversationSource::Slack, '2026-06-11 11:00:00', ['handoff_status' => 'pending']);

    $reply = fn (Conversation $conversation, ?bool $found, ?float $confidence, string $at): Message => Message::factory()->create([
        'chat_conversation_id' => $conversation->id,
        'role' => 'assistant',
        'has_llm_found_answer' => $found,
        'confidence_score' => $confidence,
        'created_at' => $at,
    ]);

    $reply($widget, true, 0.9, '2026-06-10 09:15:02');
    $reply($widget, true, 0.8, '2026-06-10 09:20:01');
    $reply($hosted, false, 0.4, '2026-06-12 14:00:05');
    $reply($telegram, true, null, '2026-06-14 20:10:03');
    $reply($noiseConversation, false, 0.1, '2026-06-11 11:00:01');

    $form = FormDefinition::factory()->forAgent($alpha)->create(['slug' => 'contact-us', 'is_active' => true]);
    FormSubmission::factory()->forDefinition($form)->submitted()->create(['chat_conversation_id' => $widget->id, 'created_at' => '2026-06-10 09:30:00']);
    FormSubmission::factory()->forDefinition($form)->submitted()->create(['chat_conversation_id' => $hosted->id, 'created_at' => '2026-06-12 14:05:00']);
    FormSubmission::factory()->forDefinition($form)->create(['status' => FormSubmissionStatus::Pending, 'chat_conversation_id' => $hosted->id, 'created_at' => '2026-06-12 14:06:00']);

    $betaForm = FormDefinition::factory()->forAgent($beta)->create(['slug' => 'callback', 'is_active' => true]);
    FormSubmission::factory()->forDefinition($betaForm)->submitted()->create(['created_at' => '2026-05-21 08:05:00']);
}

function seededAnalyticsDb(): Connection
{
    return DB::connection(config('chatvia.analytics.connection'));
}

/**
 * Act as the owner-facing analytics chat for this workspace (or for nobody),
 * resolved from the signed identity header exactly as the middleware does.
 */
function analyticsCaller(?User $workspace): void
{
    $request = Request::create('/mcp/account-analytics', 'POST');
    if ($workspace instanceof User) {
        $request->headers->set('X-Chatvia-Workspace-Id', $workspace->external_id);
    }

    app()->instance(McpCallerContext::class, app(McpCallerResolver::class)->resolve($request));
}

/**
 * The tool's JSON-RPC result as pretty JSON, with the run-specific agent ids
 * replaced by stable names.
 *
 * @param  array<string, Agent>  $agents
 */
function analyticsToolResult(TestResponse $response, array $agents): string
{
    $body = (fn (): array => $this->response->toArray())->call($response);
    $json = json_encode($body['result'] ?? $body['error'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    foreach ($agents as $name => $agent) {
        $json = str_replace('"agent_id": '.$agent->id.',', '"agent_id": "@'.$name.'",', $json);
    }

    return $json;
}

dataset('analytics tool calls', [
    'overview, current cycle' => [GetAccountOverviewTool::class, []],
    'overview, all time' => [GetAccountOverviewTool::class, ['period' => 'all_time']],
    'overview, one agent' => [GetAccountOverviewTool::class, ['agent' => '@alpha']],
    'overview, unknown agent' => [GetAccountOverviewTool::class, ['agent' => 'no-such-agent']],
    'overview, invalid period' => [GetAccountOverviewTool::class, ['period' => 'yesterday']],
    'message stats, current cycle' => [GetMessageStatsTool::class, []],
    'message stats, one agent' => [GetMessageStatsTool::class, ['agent' => '@beta']],
    'message stats, last 7 days' => [GetMessageStatsTool::class, ['period' => 'last_7_days']],
    'billing cycle usage' => [GetBillingCycleUsageTool::class, []],
    'conversation stats, current cycle' => [GetConversationStatsTool::class, []],
    'conversation stats, one agent' => [GetConversationStatsTool::class, ['agent' => '@alpha']],
    'conversation stats, empty window' => [GetConversationStatsTool::class, ['start_date' => '2025-01-01', 'end_date' => '2025-01-02']],
    'top users, current cycle' => [GetTopUsersTool::class, []],
    'top users, limit 1' => [GetTopUsersTool::class, ['limit' => 1]],
    'channel breakdown, current cycle' => [GetChannelBreakdownTool::class, []],
    'channel breakdown, one agent' => [GetChannelBreakdownTool::class, ['agent' => '@beta']],
    'agent breakdown, current cycle' => [GetAgentBreakdownTool::class, []],
    'agent breakdown, previous cycle' => [GetAgentBreakdownTool::class, ['period' => 'previous_cycle']],
    'geography, current cycle' => [GetGeographyStatsTool::class, []],
    'geography, limit 1' => [GetGeographyStatsTool::class, ['limit' => 1]],
    'activity patterns, current cycle' => [GetActivityPatternsTool::class, []],
    'activity patterns, one day' => [GetActivityPatternsTool::class, ['start_date' => '2026-06-14', 'end_date' => '2026-06-14']],
    'satisfaction, current cycle' => [GetSatisfactionStatsTool::class, []],
    'satisfaction, one agent' => [GetSatisfactionStatsTool::class, ['agent' => '@alpha']],
    'response time, current cycle' => [GetResponseTimeStatsTool::class, []],
    'response time, unknown agent' => [GetResponseTimeStatsTool::class, ['agent' => 'no-such-agent']],
    'response time, one agent' => [GetResponseTimeStatsTool::class, ['agent' => '@beta']],
    'top topics, current cycle' => [GetTopTopicsTool::class, []],
    'top topics, limit 1' => [GetTopTopicsTool::class, ['limit' => 1]],
    'lead stats, current cycle' => [GetLeadStatsTool::class, []],
    'lead stats, all time' => [GetLeadStatsTool::class, ['period' => 'all_time']],
    'handoff stats, current cycle' => [GetHandoffStatsTool::class, []],
    'token usage, current cycle' => [GetTokenUsageTool::class, []],
    'token usage, one agent' => [GetTokenUsageTool::class, ['agent' => '@alpha']],
    'token usage, empty window' => [GetTokenUsageTool::class, ['period' => 'today']],
    'ai usage, current cycle' => [GetAiUsageTool::class, []],
    'ai usage, limit 1' => [GetAiUsageTool::class, ['limit' => 1]],
]);

it('returns a stable result for each analytics tool', function (string $tool, array $arguments): void {
    $seeded = seedAnalyticsWorkspace();
    $agents = ['alpha' => $seeded['alpha'], 'beta' => $seeded['beta']];

    if (isset($arguments['agent']) && str_starts_with($arguments['agent'], '@')) {
        $arguments['agent'] = $agents[substr($arguments['agent'], 1)]->external_id;
    }

    analyticsCaller($seeded['workspace']);

    expect(analyticsToolResult(AccountAnalyticsServer::tool($tool, $arguments), $agents))->toMatchSnapshot();
})->with('analytics tool calls');

it('reports no active billing cycle for a workspace without credits', function (): void {
    $workspace = User::factory()->create(['type' => UserType::Workspace]);
    analyticsCaller($workspace);

    expect(analyticsToolResult(AccountAnalyticsServer::tool(GetBillingCycleUsageTool::class), []))->toMatchSnapshot();
});

it('refuses every analytics tool without a workspace identity', function (string $tool): void {
    analyticsCaller(null);

    AccountAnalyticsServer::tool($tool)->assertHasErrors(['No workspace context for this analytics request.']);
})->with([
    GetAccountOverviewTool::class,
    GetActivityPatternsTool::class,
    GetAgentBreakdownTool::class,
    GetAiUsageTool::class,
    GetBillingCycleUsageTool::class,
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

it('answers activity patterns on Postgres (the `hour` alias needs AS)', function (): void {
    analyticsCaller(seedAnalyticsWorkspace()['workspace']);

    AccountAnalyticsServer::tool(GetActivityPatternsTool::class)
        ->assertHasNoErrors()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('peak_hour_utc', 9)
            ->where('busiest_weekday', 'Sunday')
            ->etc());
});

it('reports zeros, not the whole account, for an agent the workspace does not own', function (): void {
    $seeded = seedAnalyticsWorkspace();
    $foreign = Agent::factory()->create();
    analyticsCaller($seeded['workspace']);

    AccountAnalyticsServer::tool(GetMessageStatsTool::class, ['agent' => $foreign->external_id])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('total_messages', 0)
            ->where('previous_total', 0)
            ->etc());
});

it('narrows the first-response time to the requested agent', function (): void {
    $seeded = seedAnalyticsWorkspace();
    analyticsCaller($seeded['workspace']);

    // Beta's only conversation got its first reply 3 seconds in; the account
    // average (Alpha's 2s and 5s included) is 3.3s.
    AccountAnalyticsServer::tool(GetResponseTimeStatsTool::class, ['agent' => $seeded['beta']->external_id])
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('avg_first_response_seconds', 3)
            ->etc());
});
