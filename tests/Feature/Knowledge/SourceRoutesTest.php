<?php

use App\Identity\Workspace\Models\User;
use Database\Factories\AgentFileFactory;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Membership\Team\Services\TeamService;

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->owner->refresh();

    $this->agent = createAgent($this->owner);
});

it('gates the source pages by their training permission', function (string $permission, string $route): void {
    $blocked = teammateWithPermissions($this->owner, []);
    $allowed = teammateWithPermissions($this->owner, [$permission]);

    $this->actingAs($blocked)->get(route($route, $this->agent))->assertForbidden();
    $this->actingAs($allowed)->get(route($route, $this->agent))->assertOk();
})->with([
    'files' => ['agent.sources.train.files', 'agents.sources.files'],
    'files data' => ['agent.sources.train.files', 'agents.sources.files.data'],
    'audio' => ['agent.sources.train.audio', 'agents.sources.audio'],
    'video' => ['agent.sources.train.video', 'agents.sources.video'],
    'web' => ['agent.sources.train.web', 'agents.sources.web'],
]);

it('gates file uploads by agent.sources.train.files', function (): void {
    $blocked = teammateWithPermissions($this->owner, []);

    $this->actingAs($blocked)
        ->postJson(route('agents.sources.files.upload', $this->agent))
        ->assertForbidden();
});

it('does not delete a file the agent does not use', function (): void {
    $file = AgentFileFactory::new()->create(['workspace_id' => $this->owner->workspace()->id]);

    $this->actingAs($this->owner)
        ->deleteJson(route('agents.sources.files.delete', [$this->agent, $file->id]))
        ->assertNotFound();

    expect(SourceFile::query()->find($file->id))->not->toBeNull();
});

it('lists the workspace pools to a teammate who may train those kinds', function (): void {
    $file = AgentFileFactory::new()->create(['workspace_id' => $this->owner->workspace()->id, 'status' => 'ingested', 'filetype' => 'pdf']);
    $website = SourceWebsite::query()->create(['workspace_id' => $this->owner->workspace()->id, 'url' => 'https://docs.example.test', 'status' => 'ingested']);
    $teammate = teammateWithPermissions($this->owner, ['agent.sources.train.files', 'agent.sources.train.web']);

    $this->actingAs($teammate)
        ->getJson(route('agents.sources.list', [$this->agent, 'type' => 'pdf']))
        ->assertOk()
        ->assertJsonPath('0.id', $file->id);

    $this->actingAs($teammate)
        ->getJson(route('agents.sources.trained-websites.index', $this->agent))
        ->assertOk()
        ->assertJsonPath('0.id', $website->id);
});

it('unlinks a shared file from the agent and refuses its last link', function (): void {
    $other = createAgent($this->owner);
    $shared = AgentFileFactory::new()->create(['workspace_id' => $this->owner->workspace()->id]);
    $this->agent->files()->attach($shared->id);
    $other->files()->attach($shared->id);
    $onlyHere = AgentFileFactory::new()->create(['workspace_id' => $this->owner->workspace()->id]);
    $this->agent->files()->attach($onlyHere->id);

    $this->actingAs($this->owner)
        ->postJson(route('agents.sources.files.unlink', [$this->agent, $shared->id]))
        ->assertOk();

    $this->actingAs($this->owner)
        ->postJson(route('agents.sources.files.unlink', [$this->agent, $onlyHere->id]))
        ->assertStatus(400);

    expect($this->agent->files()->whereKey($shared->id)->exists())->toBeFalse()
        ->and($other->files()->whereKey($shared->id)->exists())->toBeTrue()
        ->and($this->agent->files()->whereKey($onlyHere->id)->exists())->toBeTrue();
});

it('removes several pages of the website in one call', function (): void {
    $website = SourceWebsite::query()->create(['workspace_id' => $this->owner->workspace()->id, 'url' => 'https://docs.example.test', 'status' => 'ingested']);
    $this->agent->websites()->attach($website->id);
    $removed = $website->urls()->create(['url' => 'https://docs.example.test/a']);
    $kept = $website->urls()->create(['url' => 'https://docs.example.test/b']);

    $this->actingAs($this->owner)
        ->postJson(route('agents.sources.websites.links.bulk-destroy', [$this->agent, $website]), ['link_ids' => [$removed->id, $removed->id]])
        ->assertOk()
        ->assertJsonPath('count', 1);

    expect($website->urls()->pluck('id')->all())->toBe([$kept->id]);
});

it('refuses to remove a page of another website or too many pages at once', function (array $linkIds): void {
    $website = SourceWebsite::query()->create(['workspace_id' => $this->owner->workspace()->id, 'url' => 'https://docs.example.test', 'status' => 'ingested']);
    $this->agent->websites()->attach($website->id);
    $own = $website->urls()->create(['url' => 'https://docs.example.test/a']);
    $other = SourceWebsite::query()->create(['workspace_id' => $this->owner->workspace()->id, 'url' => 'https://other.example.test', 'status' => 'ingested']);
    $foreign = $other->urls()->create(['url' => 'https://other.example.test/a']);

    $ids = array_map(fn (string $id): int => match ($id) {
        'own' => $own->id,
        'foreign' => $foreign->id,
        default => (int) $id,
    }, $linkIds);

    $this->actingAs($this->owner)
        ->postJson(route('agents.sources.websites.links.bulk-destroy', [$this->agent, $website]), ['link_ids' => $ids])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('link_ids');

    expect($website->urls()->whereKey($own->id)->exists())->toBeTrue()
        ->and($other->urls()->whereKey($foreign->id)->exists())->toBeTrue();
})->with([
    'a page of another website' => [['own', 'foreign']],
    'an unknown id' => [['own', '999999999']],
    'more than 500 ids' => [['own', ...array_map(strval(...), range(1, 500))]],
]);
