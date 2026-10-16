<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\AgentActivity;
use Domain\Conversation\Topic\Models\Topic;
use Domain\Evaluation\Enums\EvalRunStatus;
use Domain\Evaluation\Models\EvalCase;
use Domain\Evaluation\Models\EvalRun;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Public API — the second coverage round
|--------------------------------------------------------------------------
|
| Leads (a form's submissions + CSV export), eval runs (trigger + poll, for
| CI), read-only analytics, forms CRUD, topics CRUD and the tool-call audit
| log. Permission and paging behaviour is covered by ApiPermissionTest and
| ApiListPaginationTest; this file covers the endpoints' own rules.
*/

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->agent = createAgent($this->owner);
    $this->token = $this->owner->createToken('account')->plainTextToken;
});

/** One immutable tool-call audit row of the given agent. */
function extAuditRow(Agent $agent, array $overrides = []): AgentActivity
{
    return AgentActivity::create([
        'agent_id' => $agent->id,
        'agent_name' => $agent->name,
        'workspace_id' => $agent->workspace_id,
        'trace_id' => (string) Str::uuid(),
        'tool_call_id' => 'call_'.Str::random(6),
        'tool_name' => 'get_weather',
        'tool_kind' => 'function',
        'outcome' => 'auto',
        'is_gated' => false,
        'is_error' => false,
        'arguments_truncated' => false,
        'result_truncated' => false,
        ...$overrides,
    ]);
}

describe('leads', function (): void {
    it('lists only the submitted entries of the form, with their values', function (): void {
        $form = FormDefinition::factory()->forAgent($this->agent)->create();
        FormSubmission::factory()->forDefinition($form)->create(); // pending — never listed
        $older = FormSubmission::factory()->forDefinition($form)->submitted()->create(['values' => ['name' => 'Ada', 'email' => 'ada@example.test']]);
        $newer = FormSubmission::factory()->forDefinition($form)->submitted()->create(['values' => ['name' => 'Grace', 'email' => 'grace@example.test']]);

        $this->withToken($this->token)
            ->getJson("/api/v1/agents/{$this->agent->external_id}/forms/{$form->external_id}/submissions")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->external_id)
            ->assertJsonPath('data.0.values.name', 'Grace')
            ->assertJsonPath('data.1.id', $older->external_id)
            ->assertJsonPath('data.1.submitted_at', fn (?string $at): bool => $at !== null);
    });

    it('exports submissions as formula-injection-safe CSV, whole or by ids', function (): void {
        $form = FormDefinition::factory()->forAgent($this->agent)->create();
        $evil = FormSubmission::factory()->forDefinition($form)->submitted()->create(['values' => ['name' => '=HYPERLINK("x")', 'email' => 'evil@example.test']]);
        FormSubmission::factory()->forDefinition($form)->submitted()->create(['values' => ['name' => 'Ada', 'email' => 'ada@example.test']]);

        $all = $this->withToken($this->token)
            ->get("/api/v1/agents/{$this->agent->external_id}/forms/{$form->external_id}/submissions/export")
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        expect($all->getContent())
            ->toContain('Name,Email,Message,"Submitted at"')
            ->toContain('ada@example.test')
            // The leading `=` is quoted away so a spreadsheet never runs it.
            ->toContain("'=HYPERLINK")
            ->not->toContain("\n=HYPERLINK");

        $selected = $this->withToken($this->token)
            ->get("/api/v1/agents/{$this->agent->external_id}/forms/{$form->external_id}/submissions/export?ids[]={$evil->external_id}")
            ->assertOk();

        expect($selected->getContent())->toContain('evil@example.test')->not->toContain('ada@example.test');
    });

    it('rejects an export selection that is not a list of uuids', function (): void {
        $form = FormDefinition::factory()->forAgent($this->agent)->create();

        $this->withToken($this->token)
            ->getJson("/api/v1/agents/{$this->agent->external_id}/forms/{$form->external_id}/submissions/export?ids[]=nope")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids.0']);
    });

    it("404s the submissions of another agent's form", function (): void {
        $otherForm = FormDefinition::factory()->forAgent(createAgent($this->owner))->create();

        $this->withToken($this->token)
            ->getJson("/api/v1/agents/{$this->agent->external_id}/forms/{$otherForm->external_id}/submissions")
            ->assertNotFound();
    });
});

describe('eval runs', function (): void {
    it('completes a run with no active cases right away, pollable by id', function (): void {
        $created = $this->withToken($this->token)
            ->postJson("/api/v1/agents/{$this->agent->external_id}/evals/runs")
            ->assertCreated()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.total_cases', 0);

        $this->withToken($this->token)
            ->getJson("/api/v1/agents/{$this->agent->external_id}/evals/runs/".$created->json('data.id'))
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.results', [])
            ->assertJsonPath('data.finished_at', fn (?string $at): bool => $at !== null);
    });

    it('starts a running run over the active cases and fans them out as a batch', function (): void {
        Bus::fake();
        EvalCase::create([
            'agent_id' => $this->agent->id,
            'agent_name' => $this->agent->name,
            'workspace_id' => $this->agent->workspace_id,
            'name' => 'Refunds',
            'input' => 'How long do refunds take?',
            'scorer_type' => 'contains',
            'scorer_config' => ['value' => '5 days'],
            'is_active' => true,
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v1/agents/{$this->agent->external_id}/evals/runs")
            ->assertCreated()
            ->assertJsonPath('data.status', 'running')
            ->assertJsonPath('data.total_cases', 1)
            ->assertJsonMissingPath('data.0.id');

        Bus::assertBatchCount(1);
    });

    it('lists the runs without the per-case results', function (): void {
        $run = EvalRun::create([
            'agent_id' => $this->agent->id,
            'agent_name' => $this->agent->name,
            'workspace_id' => $this->agent->workspace_id,
            'status' => EvalRunStatus::Completed,
            'total_cases' => 1,
            'started_at' => now(),
        ]);
        $run->results()->create([
            'case_id' => null,
            'case_name' => 'Refunds',
            'scorer_type' => 'contains',
            'has_passed' => true,
            'score' => 1.0,
        ]);

        $this->withToken($this->token)
            ->getJson("/api/v1/agents/{$this->agent->external_id}/evals/runs")
            ->assertOk()
            ->assertJsonPath('data.0.id', $run->external_id)
            ->assertJsonPath('data.0.results', null);

        $this->withToken($this->token)
            ->getJson("/api/v1/agents/{$this->agent->external_id}/evals/runs/{$run->external_id}")
            ->assertOk()
            ->assertJsonPath('data.results.0.case_name', 'Refunds')
            ->assertJsonPath('data.results.0.pass', true);
    });
});

describe('analytics', function (): void {
    it('answers each analytics endpoint with its report shape', function (string $uri, string $key, mixed $empty): void {
        $this->withToken($this->token)
            ->getJson("/api/v1/agents/{$this->agent->external_id}/analytics/{$uri}")
            ->assertOk()
            ->assertJsonPath("data.{$key}", $empty);
    })->with([
        'overview' => ['overview', 'total_messages', 0],
        'sentiment' => ['sentiment', 'totals.total', 0],
        'tone' => ['tone', 'totals.total', 0],
        'topics' => ['topics', 'total_count', 0],
        'sources' => ['sources', 'knowledge', []],
    ]);

    it('rejects a back-to-front date range', function (): void {
        $this->withToken($this->token)
            ->getJson("/api/v1/agents/{$this->agent->external_id}/analytics/overview?start_date=2026-10-10&end_date=2026-10-01")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date']);
    });
});

describe('forms CRUD', function (): void {
    $body = fn (): array => [
        'name' => 'Contact us',
        'form_type' => 'lead',
        'schema' => [
            ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
            ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
        ],
        'lead_field_map' => ['name' => 'name', 'email' => 'email'],
    ];

    it('creates a form with a derived slug and shows it back', function () use ($body): void {
        $created = $this->withToken($this->token)
            ->postJson("/api/v1/agents/{$this->agent->external_id}/forms", $body())
            ->assertCreated()
            ->assertJsonPath('data.slug', 'contact-us')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.submissions_count', 0);

        $this->withToken($this->token)
            ->getJson("/api/v1/agents/{$this->agent->external_id}/forms/".$created->json('data.id'))
            ->assertOk()
            ->assertJsonPath('data.name', 'Contact us')
            ->assertJsonPath('data.schema.1.key', 'email');
    });

    it('updates partially: an omitted field keeps its value and the slug never changes', function () use ($body): void {
        $id = $this->withToken($this->token)
            ->postJson("/api/v1/agents/{$this->agent->external_id}/forms", $body())
            ->json('data.id');

        $this->withToken($this->token)
            ->patchJson("/api/v1/agents/{$this->agent->external_id}/forms/{$id}", ['name' => 'Talk to sales', 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.name', 'Talk to sales')
            ->assertJsonPath('data.slug', 'contact-us')
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.schema.0.key', 'name');
    });

    it('rejects a schema field of an unknown type', function () use ($body): void {
        $this->withToken($this->token)
            ->postJson("/api/v1/agents/{$this->agent->external_id}/forms", [
                ...$body(),
                'schema' => [['key' => 'file', 'label' => 'File', 'type' => 'upload']],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['schema.0.type']);
    });

    it('deletes a form with its submissions', function () use ($body): void {
        $id = $this->withToken($this->token)
            ->postJson("/api/v1/agents/{$this->agent->external_id}/forms", $body())
            ->json('data.id');

        $this->withToken($this->token)
            ->deleteJson("/api/v1/agents/{$this->agent->external_id}/forms/{$id}")
            ->assertNoContent();

        expect(FormDefinition::query()->where('external_id', $id)->exists())->toBeFalse();
    });
});

describe('topics CRUD', function (): void {
    it('lists the agent\'s topics by default and every attachable one with scope=available', function (): void {
        $mine = Topic::query()->create(['workspace_id' => $this->agent->workspace_id, 'topic' => 'Shipping', 'description' => 'Delivery questions']);
        $this->agent->topics()->attach($mine->id);

        $this->withToken($this->token)
            ->getJson("/api/v1/agents/{$this->agent->external_id}/topics")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->external_id)
            ->assertJsonPath('data.0.is_system', false);

        $available = $this->withToken($this->token)
            ->getJson("/api/v1/agents/{$this->agent->external_id}/topics?scope=available&per_page=100")
            ->assertOk();

        expect(collect($available->json('data'))->pluck('is_system')->unique()->sort()->values()->all())->toBe([false, true]);
    });

    it('attaches a system topic by id once, and refuses the repeat', function (): void {
        $system = Topic::query()->system()->firstOrFail();

        $this->withToken($this->token)
            ->postJson("/api/v1/agents/{$this->agent->external_id}/topics", ['topic_id' => $system->external_id])
            ->assertCreated()
            ->assertJsonPath('data.is_system', true);

        $this->withToken($this->token)
            ->postJson("/api/v1/agents/{$this->agent->external_id}/topics", ['topic_id' => $system->external_id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['topic_id']);
    });

    it("rejects attaching another workspace's topic by id", function (): void {
        $foreign = Topic::query()->create(['workspace_id' => createAgent(User::factory()->create())->workspace_id, 'topic' => 'Secret', 'description' => 'Not yours']);

        $this->withToken($this->token)
            ->postJson("/api/v1/agents/{$this->agent->external_id}/topics", ['topic_id' => $foreign->external_id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['topic_id']);
    });

    it('creates a workspace topic and refuses a duplicate name', function (): void {
        $this->withToken($this->token)
            ->postJson("/api/v1/agents/{$this->agent->external_id}/topics", ['topic' => 'Warranty', 'description' => 'Warranty questions'])
            ->assertCreated()
            ->assertJsonPath('data.topic', 'Warranty')
            ->assertJsonPath('data.is_system', false);

        $this->withToken($this->token)
            ->postJson("/api/v1/agents/{$this->agent->external_id}/topics", ['topic' => 'Warranty', 'description' => 'Again'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['topic']);
    });

    it('renames a workspace topic but never a system one', function (): void {
        $mine = Topic::query()->create(['workspace_id' => $this->agent->workspace_id, 'topic' => 'Shipping', 'description' => 'Delivery questions']);
        $system = Topic::query()->system()->firstOrFail();
        $this->agent->topics()->attach([$mine->id, $system->id]);

        $this->withToken($this->token)
            ->patchJson("/api/v1/agents/{$this->agent->external_id}/topics/{$mine->external_id}", ['topic' => 'Delivery', 'description' => 'Delivery and tracking'])
            ->assertOk()
            ->assertJsonPath('data.topic', 'Delivery');

        $this->withToken($this->token)
            ->patchJson("/api/v1/agents/{$this->agent->external_id}/topics/{$system->external_id}", ['topic' => 'Hijacked', 'description' => 'Nope'])
            ->assertForbidden();

        expect($system->fresh()->topic)->not->toBe('Hijacked');
    });

    it('detaches a topic; an unused workspace topic dies, a system topic survives', function (): void {
        $mine = Topic::query()->create(['workspace_id' => $this->agent->workspace_id, 'topic' => 'Shipping', 'description' => 'Delivery questions']);
        $system = Topic::query()->system()->firstOrFail();
        $this->agent->topics()->attach([$mine->id, $system->id]);

        $this->withToken($this->token)
            ->deleteJson("/api/v1/agents/{$this->agent->external_id}/topics/{$mine->external_id}")
            ->assertNoContent();
        $this->withToken($this->token)
            ->deleteJson("/api/v1/agents/{$this->agent->external_id}/topics/{$system->external_id}")
            ->assertNoContent();

        expect(Topic::query()->whereKey($mine->id)->exists())->toBeFalse()
            ->and(Topic::query()->whereKey($system->id)->exists())->toBeTrue()
            ->and($this->agent->topics()->count())->toBe(0);
    });

    it('404s updating a topic the agent does not use', function (): void {
        $unattached = Topic::query()->create(['workspace_id' => $this->agent->workspace_id, 'topic' => 'Idle', 'description' => 'Not attached']);

        $this->withToken($this->token)
            ->patchJson("/api/v1/agents/{$this->agent->external_id}/topics/{$unattached->external_id}", ['topic' => 'Renamed', 'description' => 'Still idle'])
            ->assertNotFound();
    });
});

describe('audit log', function (): void {
    it('scopes the agent log to the agent and the workspace log to every agent', function (): void {
        $other = createAgent($this->owner, ['name' => 'Other']);
        extAuditRow($this->agent, ['tool_name' => 'mine']);
        extAuditRow($other, ['tool_name' => 'other']);

        $agentLog = $this->withToken($this->token)
            ->getJson("/api/v1/agents/{$this->agent->external_id}/audit")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.tool_name', 'mine')
            ->assertJsonPath('data.0.agent_id', $this->agent->external_id);

        // Rows expose public ids only — no integer primary keys.
        expect($agentLog->json('data.0'))->not->toHaveKey('id');

        $workspace = $this->withToken($this->token)->getJson('/api/v1/audit')->assertOk();
        expect(collect($workspace->json('data'))->pluck('tool_name')->sort()->values()->all())->toBe(['mine', 'other']);
    });

    it('filters by outcome and connector, and rejects an unknown outcome', function (): void {
        extAuditRow($this->agent, ['outcome' => 'denied', 'is_gated' => true]);
        extAuditRow($this->agent, ['outcome' => 'auto', 'connector_slug' => 'slack', 'connector_name' => 'Slack', 'tool_kind' => 'mcp']);

        $this->withToken($this->token)
            ->getJson("/api/v1/agents/{$this->agent->external_id}/audit?outcome=denied")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.outcome', 'denied');

        $this->withToken($this->token)
            ->getJson('/api/v1/audit?connector=slack')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.connector_name', 'Slack');

        $this->withToken($this->token)
            ->getJson('/api/v1/audit?outcome=exploded')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['outcome']);
    });

    it('pages the log newest first and walks it with the cursor', function (): void {
        $first = extAuditRow($this->agent);
        $second = extAuditRow($this->agent);

        $page = $this->withToken($this->token)
            ->getJson("/api/v1/agents/{$this->agent->external_id}/audit?per_page=1")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.trace_id', $second->trace_id);

        $this->withToken($this->token)
            ->getJson("/api/v1/agents/{$this->agent->external_id}/audit?".http_build_query(['per_page' => 1, 'cursor' => $page->json('next_cursor')]))
            ->assertOk()
            ->assertJsonPath('data.0.trace_id', $first->trace_id)
            ->assertJsonPath('next_cursor', null);
    });
});
