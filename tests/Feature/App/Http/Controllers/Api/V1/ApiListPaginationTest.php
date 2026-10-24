<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Agent\Prompt\Models\AgentPrompt;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Topic\Models\Topic;
use Domain\Evaluation\Models\EvalRun;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Membership\Team\Actions\Team\CreateTeamAction;
use Domain\Membership\Team\Data\CreateTeamData;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Uri;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * Every list of the public API is cursor-paged: it answers `{data,
 * next_cursor}` with at most `per_page` items (default 25, clamped to
 * 1–100), newest first, and a cursor that walks the rest without skipping or
 * repeating an item. A cursor the list did not hand out starts again at the
 * first page. The source list and the audit logs page on their own columns
 * and are tested with their controllers.
 */
uses(ActsAsApiOwner::class);

beforeEach(function (): void {
    $this->actAsApiOwner();
});

/**
 * Three records of one list, made oldest first, and the list's URI.
 *
 * @return array{0: string, 1: list<string>} the URI and the ids, newest first
 */
function pagedRecords(string $list, User $owner, Agent $agent): array
{
    $agentPath = '/api/v1/agents/'.$agent->external_id;

    $ids = match ($list) {
        'agents' => [$agent->external_id, createAgent($owner)->external_id, createAgent($owner)->external_id],
        'teams' => [
            $owner->primaryTeam()->external_id,
            ...array_map(fn (string $name): string => app(CreateTeamAction::class)->execute(new CreateTeamData($owner->workspace(), $name))->external_id, ['Sales', 'Support']),
        ],
        'functions' => AgentFunction::factory()->count(3)->create(['workspace_id' => $agent->workspace_id])->pluck('external_id')->all(),
        'mcps' => AgentMcpServer::factory()->count(3)->create(['workspace_id' => $agent->workspace_id])->pluck('external_id')->all(),
        'agent functions' => AgentFunction::factory()->count(3)->attachedTo($agent)->create()->pluck('external_id')->all(),
        'agent mcps' => AgentMcpServer::factory()->count(3)->attachedTo($agent)->create()->pluck('external_id')->all(),
        'users' => [$owner->external_id, ...User::factory()->employee($owner)->count(2)->create()->pluck('external_id')->all()],
        'chatters' => Chatter::factory()->count(3)->create(['workspace_id' => $owner->workspace()->id])->pluck('external_id')->all(),
        'prompts' => AgentPrompt::factory()->count(3)->create(['workspace_id' => $owner->workspace()->id])->pluck('external_id')->all(),
        'conversation messages' => Message::factory()->count(3)->create(['chat_conversation_id' => pagedConversation($agent)->id])->pluck('external_id')->all(),
        'forms' => FormDefinition::factory()->count(3)->forAgent($agent)->create()->pluck('external_id')->all(),
        'form submissions' => FormSubmission::factory()->count(3)->forDefinition(pagedForm($agent))->submitted()->create()->pluck('external_id')->all(),
        'eval runs' => EvalRun::factory()->count(3)->forAgent($agent)->completed()->create()->pluck('external_id')->all(),
        'topics' => Topic::factory()->count(3)->attachedTo($agent)->create()->pluck('external_id')->all(),
    };

    $uri = match ($list) {
        'agents', 'teams', 'functions', 'mcps', 'users', 'chatters', 'prompts' => '/api/v1/'.$list,
        'agent functions' => $agentPath.'/functions',
        'agent mcps' => $agentPath.'/mcps',
        'conversation messages' => $agentPath.'/conversations/'.pagedConversation($agent)->external_id.'/messages',
        'forms' => $agentPath.'/forms',
        'form submissions' => $agentPath.'/forms/'.pagedForm($agent)->external_id.'/submissions',
        'eval runs' => $agentPath.'/evals/runs',
        'topics' => $agentPath.'/topics',
    };

    return [$uri, array_reverse($ids)];
}

/** The one form these paging runs put submissions in (made on first use). */
function pagedForm(Agent $agent): FormDefinition
{
    return FormDefinition::query()->where('agent_id', $agent->id)->first()
        ?? FormDefinition::factory()->forAgent($agent)->create();
}

/** The one conversation these paging runs put messages in (made on first use). */
function pagedConversation(Agent $agent): Conversation
{
    return Conversation::query()->where('agent_id', $agent->id)->first()
        ?? Conversation::factory()->create(['agent_id' => $agent->id, 'source' => 'api', 'is_visible' => true]);
}

dataset('paged lists', ['agents', 'teams', 'functions', 'mcps', 'agent functions', 'agent mcps', 'users', 'chatters', 'prompts', 'conversation messages', 'forms', 'form submissions', 'eval runs', 'topics']);

it('pages a list newest first and walks it with the cursor', function (string $list): void {
    [$uri, $newestFirst] = pagedRecords($list, $this->owner, $this->agent);

    $first = $this->withToken($this->token)->getJson($uri.'?per_page=2')
        ->assertOk()
        ->assertJsonStructure(['data', 'next_cursor']);

    expect(array_column($first->json('data'), 'id'))->toBe(array_slice($newestFirst, 0, 2))
        ->and($first->json('next_cursor'))->toBeString();

    $second = $this->withToken($this->token)
        ->getJson(Uri::of($uri)->withQuery(['per_page' => 2, 'cursor' => $first->json('next_cursor')])->value())
        ->assertOk();

    expect(array_column($second->json('data'), 'id'))->toBe(array_slice($newestFirst, 2))
        ->and($second->json('next_cursor'))->toBeNull();
})->with('paged lists');

it('answers a short list whole and clamps a per_page below one up to one item', function (string $list): void {
    [$uri] = pagedRecords($list, $this->owner, $this->agent);

    $this->withToken($this->token)->getJson($uri)->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('next_cursor', null);
    $this->withToken($this->token)->getJson($uri.'?per_page=0')->assertOk()->assertJsonCount(1, 'data');
})->with('paged lists');

it('starts again at the first page for a cursor it did not hand out', function (string $list): void {
    [$uri, $newestFirst] = pagedRecords($list, $this->owner, $this->agent);

    // The column the list's own cursor carries, e.g. `agents.id`, with a
    // value of the wrong kind: the query would fail on it. The other shapes
    // of a foreign cursor are in CursorPageTest.
    $handedOut = Cursor::fromEncoded($this->withToken($this->token)->getJson($uri.'?per_page=1')->json('next_cursor'));
    $foreign = new Cursor([array_key_first($handedOut->toArray()) => 'abc'])->encode();

    $page = $this->withToken($this->token)->getJson(Uri::of($uri)->withQuery(['per_page' => 2, 'cursor' => $foreign])->value())->assertOk();

    expect(array_column($page->json('data'), 'id'))->toBe(array_slice($newestFirst, 0, 2));
})->with('paged lists');

it('defaults to 25 items and caps per_page at 100', function (): void {
    AgentPrompt::factory()->count(101)->create(['workspace_id' => $this->owner->workspace()->id]);

    $this->withToken($this->token)->getJson('/api/v1/prompts')->assertOk()->assertJsonCount(25, 'data');
    $this->withToken($this->token)->getJson('/api/v1/prompts?per_page=500')->assertOk()->assertJsonCount(100, 'data');
});

it('rejects a per_page that is not a number', function (): void {
    $this->withToken($this->token)->getJson('/api/v1/agents?per_page=many')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('per_page');
});
