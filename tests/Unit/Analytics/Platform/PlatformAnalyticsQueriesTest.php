<?php

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;
use Carbon\CarbonImmutable;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Data\Platform\ActivityBucketData;
use Domain\Analytics\Data\Platform\PlatformScopeData;
use Domain\Analytics\Data\Platform\TopAgentData;
use Domain\Analytics\Data\Platform\TopWorkspaceData;
use Domain\Analytics\Enums\AnalyticsPeriod;
use Domain\Analytics\Enums\TimeBucket;
use Domain\Analytics\Queries\Platform\PlatformActivityQuery;
use Domain\Analytics\Queries\Platform\PlatformChannelQuery;
use Domain\Analytics\Queries\Platform\PlatformOverviewQuery;
use Domain\Analytics\Queries\Platform\TopAgentsQuery;
use Domain\Analytics\Queries\Platform\TopWorkspacesQuery;
use Domain\Analytics\Services\Period;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Form\Enums\FormSubmissionStatus;
use Domain\Form\Models\FormSubmission;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The platform-analytics read models behind the admin panel's dashboard:
 * numbers across every workspace, or one. The testing database is seeded,
 * so every test runs in a future month that no seeded row falls in, and
 * install-wide totals are compared against a baseline.
 */
function paRollup(Agent $agent, string $bucketHour, array $values = []): void
{
    DB::connection(config('chatvia.analytics.connection'))
        ->table('analytics_agent_hourly_rollups')
        ->insert(array_merge([
            'agent_id' => $agent->id,
            'workspace_id' => $agent->workspace_id,
            'bucket_hour' => $bucketHour,
            'country_code' => '--',
            'llm_external_name' => '',
        ], $values));
}

function paSessionFirst(Agent $agent, string $firstSeenAt): void
{
    DB::connection(config('chatvia.analytics.connection'))
        ->table('analytics_session_firsts')
        ->insert([
            'agent_id' => $agent->id,
            'workspace_id' => $agent->workspace_id,
            'session_external_id' => (string) Str::uuid(),
            'first_seen_at' => $firstSeenAt,
        ]);
}

function paConversation(Agent $agent, string $source, string $createdAt, bool $visible = true): Conversation
{
    $conversation = Conversation::factory()
        ->for(Session::factory()->state(['agent_id' => $agent->id]), 'session')
        ->create(['agent_id' => $agent->id, 'source' => $source, 'is_visible' => $visible]);

    $conversation->forceFill(['created_at' => $createdAt])->saveQuietly();

    return $conversation;
}

function paScope(AnalyticsPeriod $period = AnalyticsPeriod::ThisMonth, ?int $workspaceId = null): PlatformScopeData
{
    $now = CarbonImmutable::now();

    $window = match ($period) {
        AnalyticsPeriod::Today => new Period($now->startOfDay(), $now, 'today', $period),
        AnalyticsPeriod::Last7Days => new Period($now->subDays(6)->startOfDay(), $now, 'the last 7 days', $period),
        AnalyticsPeriod::ThisMonth => new Period($now->startOfMonth(), $now, 'this month', $period),
        AnalyticsPeriod::AllTime => new Period(CarbonImmutable::create(2020, 1, 1), $now, 'all time', $period),
        default => throw new InvalidArgumentException('Not used in these tests.'),
    };

    return new PlatformScopeData($window, $workspaceId);
}

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2031-03-20 15:30:00', 'UTC'));
});

it('sums traffic, sign-ups, agents and leads across every workspace', function (): void {
    $baseWorkspaces = Workspace::query()->count();
    $baseAgents = Agent::query()->count();

    $acme = User::factory()->workspace()->create();
    $globex = User::factory()->workspace()->create();
    // Signed up in the window before, with no traffic.
    User::factory()->workspace()->create()->workspace()
        ->forceFill(['created_at' => '2031-02-20 09:00:00'])->saveQuietly();
    $acmeBot = createAgent($acme);
    $globexBot = createAgent($globex);

    // This month (2031-03-01 → now): 30 + 12 messages, 5 conversations.
    paRollup($acmeBot, '2031-03-10 10:00:00', ['user_messages' => 10, 'assistant_messages' => 20, 'conversations_started' => 3, 'response_ms_sum' => 6000, 'response_ms_count' => 4]);
    paRollup($globexBot, '2031-03-19 08:00:00', ['user_messages' => 5, 'assistant_messages' => 7, 'conversations_started' => 2, 'response_ms_sum' => 2000, 'response_ms_count' => 1]);
    // The equally long window before: 21 messages.
    paRollup($acmeBot, '2031-02-20 10:00:00', ['user_messages' => 7, 'assistant_messages' => 14, 'conversations_started' => 4]);

    paSessionFirst($acmeBot, '2031-03-12 10:00:00');
    paSessionFirst($globexBot, '2031-03-18 10:00:00');
    paSessionFirst($globexBot, '2031-02-18 10:00:00');

    FormSubmission::factory()->forAgent($acmeBot)->create(['status' => FormSubmissionStatus::Submitted]);
    FormSubmission::factory()->forAgent($acmeBot)->create(['status' => FormSubmissionStatus::Pending]);

    $overview = app(PlatformOverviewQuery::class)->get(paScope());

    expect($overview->messages->current)->toBe(42)
        ->and($overview->messages->previous)->toBe(21)
        ->and($overview->messages->changePct())->toBe(100.0)
        ->and($overview->conversations->current)->toBe(5)
        ->and($overview->conversations->previous)->toBe(4)
        ->and($overview->newVisitors)->toBe(2)
        ->and($overview->avgReplyMs)->toBe(1600)
        ->and($overview->newWorkspaces->current)->toBe(2)
        ->and($overview->newWorkspaces->previous)->toBe(1)
        ->and($overview->totalWorkspaces)->toBe($baseWorkspaces + 3)
        ->and($overview->activeWorkspaces)->toBe(2)
        ->and($overview->newAgents)->toBe(2)
        ->and($overview->totalAgents)->toBe($baseAgents + 2)
        ->and($overview->leads)->toBe(1);

});

it('narrows every number to one workspace', function (): void {
    $acme = User::factory()->workspace()->create();
    $globex = User::factory()->workspace()->create();
    $acmeBot = createAgent($acme);
    $globexBot = createAgent($globex);

    paRollup($acmeBot, '2031-03-10 10:00:00', ['user_messages' => 10, 'assistant_messages' => 20, 'conversations_started' => 3]);
    paRollup($globexBot, '2031-03-10 10:00:00', ['user_messages' => 5, 'assistant_messages' => 7, 'conversations_started' => 2]);
    paSessionFirst($globexBot, '2031-03-18 10:00:00');
    FormSubmission::factory()->forAgent($globexBot)->create(['status' => FormSubmissionStatus::Submitted]);

    $overview = app(PlatformOverviewQuery::class)->get(paScope(workspaceId: $acme->workspace()->id));

    expect($overview->messages->current)->toBe(30)
        ->and($overview->conversations->current)->toBe(3)
        ->and($overview->newVisitors)->toBe(0)
        ->and($overview->totalAgents)->toBe(1)
        ->and($overview->leads)->toBe(0)
        // Workspace counts mean nothing for one workspace.
        ->and($overview->newWorkspaces->current)->toBe(0)
        ->and($overview->totalWorkspaces)->toBe(0)
        ->and($overview->activeWorkspaces)->toBe(0);
});

it('reports no trend when the window before had nothing', function (): void {
    $bot = createAgent(User::factory()->workspace()->create());
    paRollup($bot, '2031-03-10 10:00:00', ['user_messages' => 1, 'assistant_messages' => 1]);

    $overview = app(PlatformOverviewQuery::class)->get(paScope());

    expect($overview->messages->changePct())->toBeNull()
        ->and($overview->avgReplyMs)->toBeNull();
});

it('charts the last 7 days day by day, with every day present', function (): void {
    $acme = User::factory()->workspace()->create();
    $acme->workspace()->forceFill(['created_at' => '2031-03-16 09:00:00'])->saveQuietly();
    $bot = createAgent($acme);
    $bot->forceFill(['created_at' => '2031-03-18 11:00:00'])->saveQuietly();

    paRollup($bot, '2031-03-16 09:00:00', ['user_messages' => 2, 'assistant_messages' => 3, 'conversations_started' => 1]);
    paRollup($bot, '2031-03-16 17:00:00', ['user_messages' => 1, 'assistant_messages' => 1]);
    paRollup($bot, '2031-03-20 08:00:00', ['user_messages' => 4, 'assistant_messages' => 4, 'conversations_started' => 2]);

    $series = app(PlatformActivityQuery::class)->get(paScope(AnalyticsPeriod::Last7Days));
    $byDay = collect($series)->keyBy(fn (ActivityBucketData $bucket): string => substr($bucket->start, 0, 10));

    expect($series)->toHaveCount(7)
        ->and($series[0]->label)->toBe('Mar 14')
        ->and($series[6]->label)->toBe('Mar 20')
        ->and($byDay['2031-03-16']->visitorMessages)->toBe(3)
        ->and($byDay['2031-03-16']->aiReplies)->toBe(4)
        ->and($byDay['2031-03-16']->conversations)->toBe(1)
        ->and($byDay['2031-03-16']->newWorkspaces)->toBe(1)
        ->and($byDay['2031-03-18']->newAgents)->toBe(1)
        ->and($byDay['2031-03-20']->messages())->toBe(8)
        ->and($byDay['2031-03-17']->messages())->toBe(0);
});

it('charts today hour by hour', function (): void {
    $bot = createAgent(User::factory()->workspace()->create());
    paRollup($bot, '2031-03-20 14:00:00', ['user_messages' => 2, 'assistant_messages' => 2]);

    $series = app(PlatformActivityQuery::class)->get(paScope(AnalyticsPeriod::Today));

    // 00:00 through the current hour, 15:00.
    expect($series)->toHaveCount(16)
        ->and($series[14]->label)->toBe('14:00')
        ->and($series[14]->messages())->toBe(4);
});

it('starts "all time" at the first recorded activity', function (): void {
    $acme = User::factory()->workspace()->create();
    $bot = createAgent($acme);
    paRollup($bot, '2030-11-05 10:00:00', ['user_messages' => 1, 'assistant_messages' => 1]);

    $series = app(PlatformActivityQuery::class)->get(paScope(AnalyticsPeriod::AllTime, $acme->workspace()->id));

    // Nov 2030 → Mar 2031 is over 92 days, so by month.
    expect(collect($series)->pluck('label')->all())->toBe(['Nov 2030', 'Dec 2030', 'Jan 2031', 'Feb 2031', 'Mar 2031'])
        ->and($series[0]->messages())->toBe(2);
});

it('counts conversations per channel, busiest first, skipping deleted and hidden ones', function (): void {
    $acme = User::factory()->workspace()->create();
    $globex = User::factory()->workspace()->create();
    $acmeBot = createAgent($acme);
    $globexBot = createAgent($globex);

    paConversation($acmeBot, 'widget', '2031-03-10 10:00:00');
    paConversation($acmeBot, 'widget', '2031-03-11 10:00:00');
    paConversation($acmeBot, 'whatsapp', '2031-03-12 10:00:00');
    paConversation($acmeBot, 'whatsapp', '2031-03-12 11:00:00')->delete();
    paConversation($acmeBot, 'whatsapp', '2031-03-12 12:00:00', visible: false);
    paConversation($acmeBot, 'telegram', '2031-02-12 10:00:00');
    paConversation($globexBot, 'slack', '2031-03-12 10:00:00');

    $acmeOnly = app(PlatformChannelQuery::class)->get(paScope(workspaceId: $acme->workspace()->id));

    expect($acmeOnly->totalConversations)->toBe(3)
        ->and(collect($acmeOnly->channels)->pluck('label')->all())->toBe(['Chat Widget', 'WhatsApp'])
        ->and($acmeOnly->channels[0]->conversations)->toBe(2)
        ->and($acmeOnly->channels[0]->pct)->toBe(66.7);

    $everyone = app(PlatformChannelQuery::class)->get(paScope());

    expect($everyone->totalConversations)->toBe(4);
});

it('ranks the busiest workspaces and agents, naming deleted ones as such', function (): void {
    $acme = User::factory()->workspace()->create(['name' => 'Acme']);
    $globex = User::factory()->workspace()->create(['name' => 'Globex']);
    $acmeSales = createAgent($acme, ['name' => 'Sales']);
    $acmeSupport = createAgent($acme, ['name' => 'Support']);
    $globexBot = createAgent($globex, ['name' => 'Helper']);

    paRollup($acmeSales, '2031-03-10 10:00:00', ['user_messages' => 10, 'assistant_messages' => 10, 'conversations_started' => 4]);
    paRollup($acmeSupport, '2031-03-11 10:00:00', ['user_messages' => 1, 'assistant_messages' => 1, 'conversations_started' => 1]);
    paRollup($globexBot, '2031-03-12 10:00:00', ['user_messages' => 6, 'assistant_messages' => 6, 'conversations_started' => 2]);

    // Traffic from a workspace deleted since is still counted.
    DB::connection(config('chatvia.analytics.connection'))->table('analytics_agent_hourly_rollups')->insert([
        'agent_id' => 999_999_001, 'workspace_id' => 999_999_002, 'bucket_hour' => '2031-03-12 10:00:00',
        'country_code' => '--', 'llm_external_name' => '', 'user_messages' => 1, 'assistant_messages' => 0,
    ]);

    $workspaces = app(TopWorkspacesQuery::class)->get(paScope());

    expect(collect($workspaces)->map(fn (TopWorkspaceData $row): ?string => $row->name)->all())->toBe(['Acme', 'Globex', null])
        ->and($workspaces[0]->email)->toBe($acme->email)
        ->and($workspaces[2]->email)->toBeNull()
        ->and($workspaces[0]->messages)->toBe(22)
        ->and($workspaces[0]->conversations)->toBe(5)
        ->and($workspaces[0]->agents)->toBe(2);

    $agents = app(TopAgentsQuery::class)->get(paScope(workspaceId: $acme->workspace()->id));

    expect(collect($agents)->map(fn (TopAgentData $row): ?string => $row->name)->all())->toBe(['Sales', 'Support'])
        ->and($agents[0]->workspaceName)->toBe('Acme')
        ->and($agents[0]->messages)->toBe(20);
});

it('counts an admin\'s own workspace as a workspace', function (): void {
    $admin = User::factory()->admin()->create(['name' => 'Staff']);
    paRollup(createAgent($admin), '2031-03-10 10:00:00', ['user_messages' => 3, 'assistant_messages' => 3]);

    $overview = app(PlatformOverviewQuery::class)->get(paScope());
    $workspaces = app(TopWorkspacesQuery::class)->get(paScope());

    expect($overview->activeWorkspaces)->toBe(1)
        ->and($overview->newWorkspaces->current)->toBe(1)
        ->and($workspaces[0]->name)->toBe('Staff')
        ->and($workspaces[0]->workspaceId)->toBe($admin->workspace()->id)
        ->and($workspaces[0]->email)->toBe($admin->email);
});

it('picks hours for a day or two, days up to a quarter, months beyond', function (string $start, string $end, TimeBucket $expected): void {
    expect(TimeBucket::spanning(CarbonImmutable::parse($start), CarbonImmutable::parse($end)))->toBe($expected);
})->with([
    'today' => ['2031-03-20 00:00:00', '2031-03-20 15:00:00', TimeBucket::Hour],
    'two days' => ['2031-03-19 00:00:00', '2031-03-20 23:59:59', TimeBucket::Hour],
    'a month' => ['2031-03-01 00:00:00', '2031-03-31 23:59:59', TimeBucket::Day],
    'a quarter' => ['2031-01-01 00:00:00', '2031-04-02 23:59:59', TimeBucket::Day],
    'half a year' => ['2031-01-01 00:00:00', '2031-06-30 23:59:59', TimeBucket::Month],
]);
