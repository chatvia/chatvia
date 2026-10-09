<?php

use App\Identity\Workspace\Models\User;
use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Evaluation\Models\EvalCase;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Retrieval\Services\Bm25Tokenizer;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;
use Domain\Membership\Team\Actions\Roles\CreateTeamRoleAction;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Str;
use Inertia\ResponseFactory;
use Inertia\Testing\AssertableInertia;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * HTTP-level safety net for the "From knowledge" eval flow: the bulk-create
 * route is gated by `agent.evals.manage`, the picker candidates ship only on
 * an Inertia partial reload (optional prop), and a happy-path POST persists
 * one case per picked question.
 *
 * REQUIRES the migrated + SEEDED test DB (pgvector + bm25 extensions, demo
 * user for AgentFileFactory).
 */
function evalKnowledgeTeammate(User $owner, array $permissions): User
{
    $team = $owner->primaryTeam();

    $role = app(CreateTeamRoleAction::class)->execute(new SaveTeamRoleData(
        team: $team,
        name: 'Role-'.Str::random(8),
        permissions: $permissions,
        allAgents: true,
        agentExternalIds: [],
    ));

    $member = User::factory()->employee($owner)->create(['current_team_id' => $team->id]);
    $team->members()->attach($member->id, ['role_id' => $role->id]);

    return $member;
}

function evalKnowledgeChunkFor(Agent $agent, string $text, ?string $questions = null): Chunk
{
    $source = AgentFileFactory::new()->create(['user_id' => $agent->user_id]);
    $source->agents()->attach($agent->id);

    return Chunk::create([
        'user_id' => $agent->user_id,
        'chunkable_type' => $source->getMorphClass(),
        'chunkable_id' => $source->getKey(),
        'chunk_index' => 1,
        'chunk' => $text,
        'questions' => $questions,
        'tokens' => 1,
        'embedding' => '['.implode(',', array_fill(0, 1024, 0)).']',
        'embedding_bm25' => Bm25Tokenizer::expression($text),
        'checksum' => Str::random(32),
    ]);
}

beforeEach(function (): void {
    $this->owner = User::factory()->create();

    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->owner->billing()->firstOrCreate([]);

    // Agent dashboard routes sit behind `user.subscribed`; subscribe the owner
    // so the gates under test are the permission ones. The price id must map
    // to a configured package — the Inertia page render resolves the plan
    // from it in shared props.
    config(['billing.packages.pro.stripe_monthly_price_id' => 'price_test']);

    $this->owner->billing->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_active',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test',
        'quantity' => 1,
    ]);
    $this->owner->refresh();

    $this->agent = createAgent($this->owner);
});

it('gates the bulk-create route by agent.evals.manage', function (): void {
    $blocked = evalKnowledgeTeammate($this->owner, []);
    $allowed = evalKnowledgeTeammate($this->owner, ['agent.evals.manage']);

    $this->actingAs($blocked)
        ->postJson(route('agents.evals.cases.from-knowledge', $this->agent), [])
        ->assertForbidden();

    $this->actingAs($allowed)
        ->postJson(route('agents.evals.cases.from-knowledge', $this->agent), [])
        ->assertStatus(422);
});

it('creates one case per picked question', function (): void {
    $chunk = evalKnowledgeChunkFor($this->agent, 'Refunds are available within 30 days.');
    $qa = AgentQuestionAnswer::create([
        'agent_id' => $this->agent->id,
        'title' => 'Refunds',
        'answer' => 'Refunds take 5 business days.',
        'questions' => 'How long do refunds take?',
    ]);

    $this->actingAs($this->owner)
        ->post(route('agents.evals.cases.from-knowledge', $this->agent), [
            'items' => [
                ['type' => 'chunk', 'id' => $chunk->id, 'question' => 'What is the refund policy?'],
                ['type' => 'qa', 'id' => $qa->id, 'question' => 'How long do refunds take?'],
            ],
        ])
        ->assertRedirect();

    $cases = EvalCase::query()->forAgent($this->agent->id)->orderBy('id')->get();

    expect($cases)->toHaveCount(2)
        ->and($cases[0]->input)->toBe('What is the refund policy?')
        ->and($cases[0]->expected_output)->toBe('Refunds are available within 30 days.')
        ->and($cases[1]->input)->toBe('How long do refunds take?')
        ->and($cases[1]->expected_output)->toBe('Refunds take 5 business days.');
});

it('omits knowledgeQuestions on first load and serves it on a partial reload with already_added flagged', function (): void {
    evalKnowledgeChunkFor($this->agent, 'Knowledge text.', questions: "What is A?\nWhat is B?");
    AgentQuestionAnswer::create([
        'agent_id' => $this->agent->id,
        'title' => 'Refunds',
        'answer' => 'Within 30 days.',
        'questions' => 'How do refunds work?',
    ]);

    EvalCase::create([
        'agent_id' => $this->agent->id,
        'agent_name' => $this->agent->name,
        'workspace_id' => $this->agent->user_id,
        'name' => 'existing',
        'input' => 'what is a?',
        'scorer_type' => 'contains',
        'scorer_config' => ['value' => 'A'],
        'active' => true,
    ]);

    // Optional prop: absent from the initial page render.
    $this->actingAs($this->owner)
        ->get(route('agents.evals', $this->agent))
        ->assertInertia(fn (Assert $page): AssertableInertia => $page
            ->component('agents/Evals/index')
            ->missing('knowledgeQuestions'));

    // Present on the partial reload the picker dialog fires.
    $response = $this->actingAs($this->owner)->get(route('agents.evals', $this->agent), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(ResponseFactory::class)->getVersion(),
        'X-Inertia-Partial-Component' => 'agents/Evals/index',
        'X-Inertia-Partial-Data' => 'knowledgeQuestions',
    ]);

    $questions = collect($response->json('props.knowledgeQuestions'));

    // QA-derived candidates come first; the question matching an existing case
    // input (case-insensitively) is flagged as already added.
    expect($questions)->toHaveCount(3)
        ->and($questions[0]['type'])->toBe('qa')
        ->and($questions[0]['question'])->toBe('How do refunds work?')
        ->and($questions[0]['source'])->toBe('Refunds')
        ->and($questions->firstWhere('question', 'What is A?')['already_added'])->toBeTrue()
        ->and($questions->firstWhere('question', 'What is A?')['source'])->toBe('File')
        ->and($questions->firstWhere('question', 'What is B?')['already_added'])->toBeFalse();
});
