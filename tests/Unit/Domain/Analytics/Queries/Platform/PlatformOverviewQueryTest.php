<?php

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Queries\Platform\PlatformOverviewQuery;
use Domain\Form\Enums\FormSubmissionStatus;
use Domain\Form\Models\FormSubmission;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Analytics\PlatformScopes;

/**
 * The headline numbers of the admin panel's dashboard, across every workspace
 * or one, each against the equally long window before. The testing database
 * is seeded, so every test runs in a future month that no seeded row falls
 * in, and install-wide totals are compared against a baseline.
 */
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2031-03-20 15:30:00', 'UTC'));
});

function poqSessionFirst(Agent $agent, string $firstSeenAt): void
{
    DB::connection(config('chatvia.analytics.connection'))
        ->table('analytics_session_firsts')
        ->insert([
            'agent_id' => $agent->id,
            'workspace_id' => $agent->workspace_id,
            'session_external_id' => (string) Str::uuid7(),
            'first_seen_at' => $firstSeenAt,
        ]);
}

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
    analyticsRollup($acmeBot, '2031-03-10 10:00:00', ['user_messages' => 10, 'assistant_messages' => 20, 'conversations_started' => 3, 'response_ms_sum' => 6000, 'response_ms_count' => 4]);
    analyticsRollup($globexBot, '2031-03-19 08:00:00', ['user_messages' => 5, 'assistant_messages' => 7, 'conversations_started' => 2, 'response_ms_sum' => 2000, 'response_ms_count' => 1]);
    // The equally long window before: 21 messages.
    analyticsRollup($acmeBot, '2031-02-20 10:00:00', ['user_messages' => 7, 'assistant_messages' => 14, 'conversations_started' => 4]);

    poqSessionFirst($acmeBot, '2031-03-12 10:00:00');
    poqSessionFirst($globexBot, '2031-03-18 10:00:00');
    poqSessionFirst($globexBot, '2031-02-18 10:00:00');

    FormSubmission::factory()->forAgent($acmeBot)->submitted()->create();
    FormSubmission::factory()->forAgent($acmeBot)->create(['status' => FormSubmissionStatus::Pending]);
    // A lead counts in the window it was submitted in.
    FormSubmission::factory()->forAgent($globexBot)->submitted()->create(['submitted_at' => '2031-02-27 10:00:00']);

    $overview = app(PlatformOverviewQuery::class)->get(PlatformScopes::of());

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

    analyticsRollup($acmeBot, '2031-03-10 10:00:00', ['user_messages' => 10, 'assistant_messages' => 20, 'conversations_started' => 3]);
    analyticsRollup($globexBot, '2031-03-10 10:00:00', ['user_messages' => 5, 'assistant_messages' => 7, 'conversations_started' => 2]);
    poqSessionFirst($globexBot, '2031-03-18 10:00:00');
    FormSubmission::factory()->forAgent($globexBot)->submitted()->create();

    $overview = app(PlatformOverviewQuery::class)->get(PlatformScopes::of(workspaceId: $acme->workspace()->id));

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
    analyticsRollup(createAgent(User::factory()->workspace()->create()), '2031-03-10 10:00:00', ['user_messages' => 1, 'assistant_messages' => 1]);

    $overview = app(PlatformOverviewQuery::class)->get(PlatformScopes::of());

    expect($overview->messages->changePct())->toBeNull()
        ->and($overview->avgReplyMs)->toBeNull();
});

it('counts an admin\'s own workspace as a workspace', function (): void {
    analyticsRollup(createAgent(User::factory()->admin()->create()), '2031-03-10 10:00:00', ['user_messages' => 3, 'assistant_messages' => 3]);

    $overview = app(PlatformOverviewQuery::class)->get(PlatformScopes::of());

    expect($overview->activeWorkspaces)->toBe(1)
        ->and($overview->newWorkspaces->current)->toBe(1);
});
