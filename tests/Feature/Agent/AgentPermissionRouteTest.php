<?php

use App\Identity\Workspace\Models\User;
use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Form\Models\FormDefinition;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\Storage;

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

it('gates reading and editing a text or Q&A pair by its training permission', function (string $perm, string $showRoute, string $updateRoute, string $kind): void {
    $blocked = teammateWithPermissions($this->owner, []);
    $allowed = teammateWithPermissions($this->owner, [$perm]);

    $record = $kind === 'text' ? aprText($this->agent) : aprQa($this->agent);

    // Route binding resolves the record before the gate, so an unknown id is
    // a 404 for everyone; with a real record, a blocked teammate stops at the
    // gate (403), a permitted one passes it and then fails the validation of
    // an empty update body (422).
    $this->actingAs($blocked)->getJson(route($showRoute, [$this->agent, $record->id]))->assertForbidden();
    $this->actingAs($allowed)->getJson(route($showRoute, [$this->agent, 999999]))->assertNotFound();
    $this->actingAs($allowed)->getJson(route($showRoute, [$this->agent, $record->id]))->assertOk();

    $this->actingAs($blocked)->patchJson(route($updateRoute, [$this->agent, $record->id]), [])->assertForbidden();
    $this->actingAs($allowed)->patchJson(route($updateRoute, [$this->agent, $record->id]), [])->assertStatus(422);
})->with([
    'text' => ['agent.sources.train.text', 'agents.sources.text.show', 'agents.sources.text.update', 'text'],
    'qa' => ['agent.sources.train.qa', 'agents.sources.qa.show', 'agents.sources.qa.update', 'qa'],
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
    $qa = aprQa($this->agent);

    // Route binding resolves `{qa}` before the gate, so probe with a real row:
    // a blocked teammate stops at the gate; an unknown id 404s for everyone.
    $this->actingAs($blocked)
        ->deleteJson(route('agents.sources.qa.destroy', [$this->agent, $qa->id]))
        ->assertForbidden();

    $this->actingAs($allowed)
        ->deleteJson(route('agents.sources.qa.destroy', [$this->agent, 999999]))
        ->assertNotFound();

    $this->actingAs($allowed)
        ->deleteJson(route('agents.sources.qa.destroy', [$this->agent, $qa->id]))
        ->assertOk();
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

// The upload has no file here, so a permitted teammate passes the gate and
// then fails the validation (422).
it('gates uploading and processing files by agent.sources.train.files', function (string $route, int $allowedStatus): void {
    $blocked = teammateWithPermissions($this->owner, []);
    $allowed = teammateWithPermissions($this->owner, ['agent.sources.train.files']);

    $this->actingAs($blocked)->postJson(route($route, $this->agent))->assertForbidden();
    $this->actingAs($allowed)->postJson(route($route, $this->agent))->assertStatus($allowedStatus);
})->with([
    'upload' => ['agents.sources.files.upload', 422],
    'process' => ['agents.sources.files.process', 200],
]);

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

it('gates linking a trained text by agent.sources.train.text and unlinking by agent.sources.delete', function (): void {
    $blocked = teammateWithPermissions($this->owner, []);
    $trainer = teammateWithPermissions($this->owner, ['agent.sources.train.text']);
    $remover = teammateWithPermissions($this->owner, ['agent.sources.delete']);

    // Route binding resolves `{text}` before the gate, so probe with a real
    // row: a blocked teammate stops at the gate, an unknown id 404s.
    $text = aprText($this->agent, attach: false);

    $this->actingAs($blocked)->postJson(route('agents.sources.texts.link', [$this->agent, $text->id]))->assertForbidden();
    $this->actingAs($trainer)->postJson(route('agents.sources.texts.link', [$this->agent, 999999]))->assertNotFound();
    $this->actingAs($trainer)->postJson(route('agents.sources.texts.link', [$this->agent, $text->id]))->assertSuccessful();
    expect($this->agent->texts()->whereKey($text->id)->exists())->toBeTrue();

    $this->actingAs($trainer)->postJson(route('agents.sources.text.unlink', [$this->agent, $text->id]))->assertForbidden();
    $this->actingAs($remover)->postJson(route('agents.sources.text.unlink', [$this->agent, 999999]))->assertNotFound();
    $this->actingAs($remover)->postJson(route('agents.sources.text.unlink', [$this->agent, $text->id]))->assertSuccessful();
    expect($this->agent->texts()->whereKey($text->id)->exists())->toBeFalse();
});

it('gates linking a trained website by agent.sources.train.web and unlinking by agent.sources.delete', function (): void {
    $blocked = teammateWithPermissions($this->owner, []);
    $trainer = teammateWithPermissions($this->owner, ['agent.sources.train.web']);
    $remover = teammateWithPermissions($this->owner, ['agent.sources.delete']);

    // The ids are looked up after the gate: a blocked teammate stops at it
    // (403), a permitted one passes it and then fails the validation (422)
    // or finds no such source on the agent (404).
    $this->actingAs($blocked)->postJson(route('agents.sources.trained-websites.link', [$this->agent, 999999]), ['website_id' => 999999])->assertForbidden();
    $this->actingAs($trainer)->postJson(route('agents.sources.trained-websites.link', [$this->agent, 999999]), ['website_id' => 999999])->assertUnprocessable();

    $this->actingAs($trainer)->postJson(route('agents.sources.trained-websites.unlink', [$this->agent, 999999]))->assertForbidden();
    $this->actingAs($remover)->postJson(route('agents.sources.trained-websites.unlink', [$this->agent, 999999]))->assertNotFound();
});

/**
 * A completed workspace text (with its stored content) for the gate probes,
 * attached to the agent unless told otherwise.
 */
function aprText(Agent $agent, bool $attach = true): SourceText
{
    $text = SourceText::query()->create([
        'workspace_id' => $agent->workspace_id,
        'filepath' => 'users/x/train/text/'.uniqid().'.txt',
        'excerpt' => 'An excerpt.',
        'length' => 1,
        'checksum' => md5(uniqid()),
        'status' => SourceStatus::Completed,
    ]);

    Storage::put($text->filepath, 'Some trained text.');

    if ($attach) {
        $agent->texts()->attach($text->id);
    }

    return $text;
}

/** One curated Q&A entry of the agent for the gate probes. */
function aprQa(Agent $agent): AgentQuestionAnswer
{
    return AgentQuestionAnswer::create([
        'agent_id' => $agent->id,
        'title' => 'Refunds',
        'answer' => 'Refunds take 5 business days.',
        'questions' => 'How long do refunds take?',
    ]);
}
