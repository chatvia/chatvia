<?php

use App\Identity\Workspace\Models\User;
use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Membership\Team\Actions\Team\CreateTeamAction;
use Domain\Membership\Team\Data\CreateTeamData;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Public API — every list is cursor-paged
|--------------------------------------------------------------------------
|
| A list answers `{data, next_cursor}`: at most `per_page` items (default 25,
| clamped to 1–100), newest first, and a cursor that walks the rest without
| skipping or repeating an item. A cursor the list did not hand out starts
| again at the first page.
*/

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->agent = createAgent($this->owner);
    $this->token = $this->owner->createToken('account')->plainTextToken;
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
        'functions' => array_map(fn (): string => AgentFunction::factory()->create(['workspace_id' => $agent->workspace_id])->external_id, range(1, 3)),
        'mcps' => array_map(fn (): string => AgentMcpServer::factory()->create(['workspace_id' => $agent->workspace_id])->external_id, range(1, 3)),
        'agent functions' => array_map(fn (): string => AgentFunction::factory()->attachedTo($agent)->create()->external_id, range(1, 3)),
        'agent mcps' => array_map(fn (): string => AgentMcpServer::factory()->attachedTo($agent)->create()->external_id, range(1, 3)),
    };

    $uri = match ($list) {
        'agents', 'teams', 'functions', 'mcps' => '/api/v1/'.$list,
        'agent functions' => $agentPath.'/functions',
        'agent mcps' => $agentPath.'/mcps',
    };

    return [$uri, array_reverse($ids)];
}

dataset('paged lists', ['agents', 'teams', 'functions', 'mcps', 'agent functions', 'agent mcps']);

it('pages a list newest first and walks it with the cursor', function (string $list): void {
    [$uri, $newestFirst] = pagedRecords($list, $this->owner, $this->agent);

    $first = $this->withToken($this->token)->getJson($uri.'?per_page=2')
        ->assertOk()
        ->assertJsonStructure(['data', 'next_cursor']);

    expect(array_column($first->json('data'), 'id'))->toBe(array_slice($newestFirst, 0, 2))
        ->and($first->json('next_cursor'))->toBeString();

    $second = $this->withToken($this->token)
        ->getJson($uri.'?'.http_build_query(['per_page' => 2, 'cursor' => $first->json('next_cursor')]))
        ->assertOk();

    expect(array_column($second->json('data'), 'id'))->toBe(array_slice($newestFirst, 2))
        ->and($second->json('next_cursor'))->toBeNull();
})->with('paged lists');

it('defaults to 25 items and clamps per_page to 1–100', function (string $list): void {
    [$uri] = pagedRecords($list, $this->owner, $this->agent);

    $this->withToken($this->token)->getJson($uri)->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('next_cursor', null);
    $this->withToken($this->token)->getJson($uri.'?per_page=0')->assertOk()->assertJsonCount(1, 'data');
    $this->withToken($this->token)->getJson($uri.'?per_page=500')->assertOk()->assertJsonCount(3, 'data');
})->with('paged lists');

it('starts again at the first page for a cursor it did not hand out', function (string $list): void {
    [$uri, $newestFirst] = pagedRecords($list, $this->owner, $this->agent);

    // The column the list's own cursor carries, e.g. `agents.id`.
    $handedOut = Cursor::fromEncoded($this->withToken($this->token)->getJson($uri.'?per_page=1')->json('next_cursor'));
    $column = array_key_first($handedOut->toArray());

    $foreign = [
        'not-a-cursor',
        new Cursor(['other' => 1])->encode(),
        new Cursor([$column => 'abc'])->encode(),
        new Cursor([$column => 1, 'other' => 1])->encode(),
        new Cursor([$column => $handedOut->parameter($column)], pointsToNextItems: false)->encode(),
    ];

    foreach ($foreign as $cursor) {
        $page = $this->withToken($this->token)->getJson($uri.'?'.http_build_query(['per_page' => 2, 'cursor' => $cursor]))->assertOk();

        expect(array_column($page->json('data'), 'id'))->toBe(array_slice($newestFirst, 0, 2));
    }
})->with('paged lists');

it('rejects a per_page that is not a number', function (): void {
    $this->withToken($this->token)->getJson('/api/v1/agents?per_page=many')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('per_page');
});

it('pages the sources of every kind together, newest first', function (): void {
    $text = SourceText::query()->create([
        'workspace_id' => $this->agent->workspace_id,
        'filepath' => 'workspaces/test/texts/'.Str::uuid()->toString().'.txt',
        'excerpt' => 'Our refund policy',
        'length' => 1,
        'checksum' => md5(Str::uuid()->toString()),
        'created_at' => now()->subDays(3),
    ]);
    $file = AgentFileFactory::new()->create(['workspace_id' => $this->agent->workspace_id, 'created_at' => now()->subDays(2)]);
    // Two sources added in the same second: the cursor still orders them.
    $sameSecond = now()->subDay()->startOfSecond();
    $website = SourceWebsite::query()->create([
        'workspace_id' => $this->agent->workspace_id,
        'url' => 'https://docs.example.test/',
        'status' => 'ingested',
        'created_at' => $sameSecond,
    ]);
    $secondFile = AgentFileFactory::new()->create(['workspace_id' => $this->agent->workspace_id, 'created_at' => $sameSecond]);
    $newestText = SourceText::query()->create([
        'workspace_id' => $this->agent->workspace_id,
        'filepath' => 'workspaces/test/texts/'.Str::uuid()->toString().'.txt',
        'excerpt' => 'Opening hours',
        'length' => 1,
        'checksum' => md5(Str::uuid()->toString()),
    ]);

    $this->agent->texts()->attach([$text->id, $newestText->id]);
    $this->agent->files()->attach([$file->id, $secondFile->id]);
    $this->agent->websites()->attach($website->id);

    // A source of another agent of the same workspace is not listed.
    createAgent($this->owner)->files()->attach(AgentFileFactory::new()->create(['workspace_id' => $this->agent->workspace_id])->id);

    $uri = "/api/v1/agents/{$this->agent->external_id}/sources";
    $seen = [];
    $cursor = null;

    do {
        $page = $this->withToken($this->token)
            ->getJson($uri.'?'.http_build_query(array_filter(['per_page' => 2, 'cursor' => $cursor])))
            ->assertOk();

        $seen = [...$seen, ...array_map(fn (array $source): string => $source['kind'].':'.$source['id'], $page->json('data'))];
        $cursor = $page->json('next_cursor');
    } while ($cursor !== null);

    // Same second: the higher id first; on equal ids `source_file` sorts
    // before `source_website`.
    $sameSecondPair = $secondFile->id >= $website->id
        ? ['file:'.$secondFile->external_id, 'url:'.$website->external_id]
        : ['url:'.$website->external_id, 'file:'.$secondFile->external_id];

    expect($seen)->toBe([
        'text:'.$newestText->external_id,
        ...$sameSecondPair,
        'file:'.$file->external_id,
        'text:'.$text->external_id,
    ]);
});

it('starts the source list again at the first page for a cursor it did not hand out', function (): void {
    $file = AgentFileFactory::new()->create(['workspace_id' => $this->agent->workspace_id]);
    $this->agent->files()->attach($file->id);

    foreach ([new Cursor(['created_at' => 'yesterday', 'sourceable_id' => 1, 'sourceable_type' => 'source_file'])->encode(), new Cursor(['id' => 1])->encode()] as $cursor) {
        $this->withToken($this->token)
            ->getJson("/api/v1/agents/{$this->agent->external_id}/sources?".http_build_query(['cursor' => $cursor]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $file->external_id);
    }
});
