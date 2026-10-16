<?php

use App\Identity\Workspace\Models\User;
use Database\Factories\AgentFileFactory;
use Domain\Form\Models\FormDefinition;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Membership\Team\Services\TeamService;

beforeEach(function (): void {
    $this->owner = User::factory()->create();

    // Factory users start bare — create the default team explicitly.
    app(TeamService::class)->createDefaultTeam($this->owner);

    $this->agent = createAgent($this->owner);
});

// [permission, index route name, store route name]
dataset('training routes', [
    'text' => ['agent.sources.train.text', 'agents.sources.text.index', 'agents.sources.text.store'],
    'qa' => ['agent.sources.train.qa', 'agents.sources.qa.index', 'agents.sources.qa.store'],
    'web' => ['agent.sources.train.web', 'agents.sources.websites.index', 'agents.sources.websites.store'],
]);

it('gates source-training index/store routes by their permission', function (string $perm, string $indexRoute, string $storeRoute): void {
    $blocked = teammateWithPermissions($this->owner, []);
    $allowed = teammateWithPermissions($this->owner, [$perm]);

    // index (read): blocked 403, allowed 200
    $this->actingAs($blocked)->getJson(route($indexRoute, $this->agent))->assertForbidden();
    $this->actingAs($allowed)->getJson(route($indexRoute, $this->agent))->assertOk();

    // store: the permission gate runs before validation, so a blocked teammate
    // gets 403 even with an empty body; a permitted one passes the gate and then
    // fails validation (422).
    $this->actingAs($blocked)->postJson(route($storeRoute, $this->agent), [])->assertForbidden();
    $this->actingAs($allowed)->postJson(route($storeRoute, $this->agent), [])->assertStatus(422);
})->with('training routes');

it('gates reading and editing a text or Q&A pair by its training permission', function (string $perm, string $showRoute, string $updateRoute): void {
    $blocked = teammateWithPermissions($this->owner, []);
    $allowed = teammateWithPermissions($this->owner, [$perm]);

    // {id} is a plain int looked up after the gate: a blocked teammate stops
    // at the gate (403), a permitted one passes it and then fails the lookup
    // (404) or the validation (422).
    $this->actingAs($blocked)->getJson(route($showRoute, [$this->agent, 999999]))->assertForbidden();
    $this->actingAs($allowed)->getJson(route($showRoute, [$this->agent, 999999]))->assertNotFound();

    $this->actingAs($blocked)->patchJson(route($updateRoute, [$this->agent, 999999]), [])->assertForbidden();
    $this->actingAs($allowed)->patchJson(route($updateRoute, [$this->agent, 999999]), [])->assertStatus(422);
})->with([
    'text' => ['agent.sources.train.text', 'agents.sources.text.show', 'agents.sources.text.update'],
    'qa' => ['agent.sources.train.qa', 'agents.sources.qa.show', 'agents.sources.qa.update'],
]);

it('gates training a website and its pages by agent.sources.train.web, and removing pages by agent.sources.delete', function (): void {
    $website = SourceWebsite::create(['workspace_id' => $this->agent->workspace_id, 'url' => 'https://docs.example.test', 'status' => 'ingested']);
    $this->agent->websites()->attach($website->id);
    $page = $website->urls()->create(['url' => 'https://docs.example.test/page']);

    $blocked = teammateWithPermissions($this->owner, []);
    $trainer = teammateWithPermissions($this->owner, ['agent.sources.train.web']);
    $remover = teammateWithPermissions($this->owner, ['agent.sources.delete']);

    $this->actingAs($blocked)->getJson(route('agents.sources.websites.links.index', [$this->agent, $website]))->assertForbidden();
    $this->actingAs($trainer)->getJson(route('agents.sources.websites.links.index', [$this->agent, $website]))->assertOk();

    $this->actingAs($blocked)->postJson(route('agents.sources.websites.train', [$this->agent, $website]))->assertForbidden();
    $this->actingAs($blocked)->postJson(route('agents.sources.websites.links.retrain', [$this->agent, $website, $page]))->assertForbidden();

    // Training a website does not let a teammate remove its pages.
    $this->actingAs($trainer)->deleteJson(route('agents.sources.websites.links.destroy', [$this->agent, $website, $page]))->assertForbidden();
    $this->actingAs($trainer)->postJson(route('agents.sources.websites.links.bulk-destroy', [$this->agent, $website]), ['ids' => [$page->id]])->assertForbidden();
    expect($website->urls()->whereKey($page->id)->exists())->toBeTrue();

    $this->actingAs($remover)->deleteJson(route('agents.sources.websites.links.destroy', [$this->agent, $website, $page]))->assertSuccessful();
    expect($website->urls()->whereKey($page->id)->exists())->toBeFalse();
});

it('gates the qa delete route by agent.sources.delete', function (): void {
    $blocked = teammateWithPermissions($this->owner, []);
    $allowed = teammateWithPermissions($this->owner, ['agent.sources.delete']);

    // {id} is a plain int, so route binding doesn't 404 before the gate runs.
    $this->actingAs($blocked)
        ->deleteJson(route('agents.sources.qa.destroy', [$this->agent, 999999]))
        ->assertForbidden();

    // permitted passes the gate; the controller's findOrFail then 404s.
    $this->actingAs($allowed)
        ->deleteJson(route('agents.sources.qa.destroy', [$this->agent, 999999]))
        ->assertNotFound();
});

it('gates the submissions list and bulk-delete by agent.leads.view', function (): void {
    $blocked = teammateWithPermissions($this->owner, []);
    $allowed = teammateWithPermissions($this->owner, ['agent.leads.view']);
    $form = FormDefinition::factory()->forAgent($this->agent)->create();

    $this->actingAs($blocked)->getJson(route('agents.forms.submissions.index', [$this->agent, $form]))->assertForbidden();
    $this->actingAs($allowed)->getJson(route('agents.forms.submissions.index', [$this->agent, $form]))->assertOk();

    // Empty body → nothing deleted → the controller redirects back (302); the
    // contrast with the blocked 403 isolates the permission gate.
    $this->actingAs($blocked)->deleteJson(route('agents.forms.submissions.destroy', [$this->agent, $form]), [])->assertForbidden();
    $this->actingAs($allowed)->deleteJson(route('agents.forms.submissions.destroy', [$this->agent, $form]), [])->assertStatus(302);
});

it('gates leads settings by agent.leads.settings', function (): void {
    $blocked = teammateWithPermissions($this->owner, []);
    $allowed = teammateWithPermissions($this->owner, ['agent.leads.settings']);

    $this->actingAs($blocked)->patchJson(route('agents.leads.settings', $this->agent), [])->assertForbidden();
    $this->actingAs($allowed)->patchJson(route('agents.leads.settings', $this->agent), [])->assertStatus(422);
});

it('gates submissions export by agent.leads.export', function (): void {
    $blocked = teammateWithPermissions($this->owner, []);
    $allowed = teammateWithPermissions($this->owner, ['agent.leads.export']);
    $form = FormDefinition::factory()->forAgent($this->agent)->create();

    $this->actingAs($blocked)->get(route('agents.forms.submissions.export', [$this->agent, $form]))->assertForbidden();
    $this->actingAs($allowed)->get(route('agents.forms.submissions.export', [$this->agent, $form]))->assertOk();
});

it('gates the chat-log conversations list by agent.chat.view', function (): void {
    $blocked = teammateWithPermissions($this->owner, []);
    $allowed = teammateWithPermissions($this->owner, ['agent.chat.view']);

    $this->actingAs($blocked)->getJson(route('agents.logs.conversations', $this->agent))->assertForbidden();
    $this->actingAs($allowed)->getJson(route('agents.logs.conversations', $this->agent))->assertOk();
});

it('gates conversation deletion by agent.chat.delete', function (): void {
    $blocked = teammateWithPermissions($this->owner, []);
    $allowed = teammateWithPermissions($this->owner, ['agent.chat.delete']);

    // {conversation} is a string param looked up (by external_id, a uuid) AFTER
    // the gate. A valid-but-nonexistent uuid keeps blocked at the gate (403) and
    // lets allowed pass it, then 404 on the lookup.
    $missing = '11111111-1111-1111-1111-111111111111';
    $this->actingAs($blocked)
        ->deleteJson(route('agents.logs.delete-conversation', [$this->agent, $missing]))
        ->assertForbidden();
    $this->actingAs($allowed)
        ->deleteJson(route('agents.logs.delete-conversation', [$this->agent, $missing]))
        ->assertNotFound();
});

it('gates file processing by agent.sources.train.files', function (): void {
    $blocked = teammateWithPermissions($this->owner, []);
    $allowed = teammateWithPermissions($this->owner, ['agent.sources.train.files']);

    $this->actingAs($blocked)->postJson(route('agents.sources.files.process', $this->agent))->assertForbidden();
    $this->actingAs($allowed)->postJson(route('agents.sources.files.process', $this->agent))->assertOk();
});

it('lets the owner through (owners hold every permission)', function (): void {
    $this->actingAs($this->owner)
        ->getJson(route('agents.sources.text.index', $this->agent))
        ->assertOk();
});

// Linking a source the workspace already trained adds to what the agent
// knows, so it needs that kind's training permission; unlinking takes
// knowledge away, so it needs agent.sources.delete, as the dashboard's
// remove dialog (unlink or delete) already does.
it('lists the workspace\'s trained sources to link only with that kind\'s training permission', function (string $perm, string $route, array $query): void {
    $blocked = teammateWithPermissions($this->owner, []);
    $allowed = teammateWithPermissions($this->owner, [$perm]);

    $this->actingAs($blocked)->getJson(route($route, [$this->agent, ...$query]))->assertForbidden();
    $this->actingAs($allowed)->getJson(route($route, [$this->agent, ...$query]))->assertOk();
})->with([
    'files' => ['agent.sources.train.files', 'agents.sources.list', ['type' => 'pdf']],
    'texts' => ['agent.sources.train.text', 'agents.sources.texts.list', []],
    'websites' => ['agent.sources.train.web', 'agents.sources.trained-websites.index', []],
]);

it('gates linking a trained file by agent.sources.train.files and unlinking it by agent.sources.delete', function (): void {
    $file = AgentFileFactory::new()->create(['workspace_id' => $this->agent->workspace_id]);
    makeTeamAgent($this->owner, 'Other')->files()->attach($file->id);

    $blocked = teammateWithPermissions($this->owner, []);
    $trainer = teammateWithPermissions($this->owner, ['agent.sources.train.files']);
    $remover = teammateWithPermissions($this->owner, ['agent.sources.delete']);

    $this->actingAs($blocked)->postJson(route('agents.sources.files.link', [$this->agent, $file->id]), ['file_id' => $file->id])->assertForbidden();
    expect($this->agent->files()->whereKey($file->id)->exists())->toBeFalse();

    $this->actingAs($trainer)->postJson(route('agents.sources.files.link', [$this->agent, $file->id]), ['file_id' => $file->id])->assertSuccessful();
    expect($this->agent->files()->whereKey($file->id)->exists())->toBeTrue();

    $this->actingAs($trainer)->postJson(route('agents.sources.files.unlink', [$this->agent, $file]))->assertForbidden();
    expect($this->agent->files()->whereKey($file->id)->exists())->toBeTrue();

    $this->actingAs($remover)->postJson(route('agents.sources.files.unlink', [$this->agent, $file]))->assertSuccessful();
    expect($this->agent->files()->whereKey($file->id)->exists())->toBeFalse();
});

it('gates linking a trained text or website by its training permission and unlinking by agent.sources.delete', function (string $train, string $linkRoute, string $unlinkRoute, string $field): void {
    $blocked = teammateWithPermissions($this->owner, []);
    $trainer = teammateWithPermissions($this->owner, [$train]);
    $remover = teammateWithPermissions($this->owner, ['agent.sources.delete']);

    // The ids are looked up after the gate: a blocked teammate stops at it
    // (403), a permitted one passes it and then fails the validation (422)
    // or finds no such source on the agent (404).
    $this->actingAs($blocked)->postJson(route($linkRoute, [$this->agent, 999999]), [$field => 999999])->assertForbidden();
    $this->actingAs($trainer)->postJson(route($linkRoute, [$this->agent, 999999]), [$field => 999999])->assertUnprocessable();

    $this->actingAs($trainer)->postJson(route($unlinkRoute, [$this->agent, 999999]))->assertForbidden();
    $this->actingAs($remover)->postJson(route($unlinkRoute, [$this->agent, 999999]))->assertNotFound();
})->with([
    'text' => ['agent.sources.train.text', 'agents.sources.texts.link', 'agents.sources.text.unlink', 'text_id'],
    'website' => ['agent.sources.train.web', 'agents.sources.trained-websites.link', 'agents.sources.trained-websites.unlink', 'website_id'],
]);
