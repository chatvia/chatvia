<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Database\Factories\AgentFileFactory;
use Database\Factories\AgentQuestionAnswerFactory;
use Domain\Agent\Jobs\PurgeAgentJob;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Events\HandedOff;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Topic\Models\ConversationTopic;
use Domain\Conversation\Topic\Models\Topic;
use Domain\Evaluation\Models\EvalCase;
use Domain\Evaluation\Models\EvalRun;
use Domain\Form\Models\FormDefinition;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

/**
 * The permission sweep of the agent routes. Each route answers 403 to a
 * teammate who holds every agent permission but the one it needs, and lets a
 * teammate with only that permission through. The gate runs before the
 * route's validation and before most lookups, so a refused teammate is
 * stopped even with an empty body, and an allowed one then gets the route's
 * own answer (422 for an empty form, 404 for an unknown id). The cases after
 * the sweep check that a refused request changes nothing, and cover the
 * routes that bind a record first or need two permissions.
 */
beforeEach(function (): void {
    withLicense();
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner);
});

// [permission, method, route name, parameters after {agent}, body, status once allowed]
dataset('agent routes', [
    // Knowledge sources
    'text list' => ['agent.sources.train.text', 'GET', 'agents.sources.text.index', [], [], 200],
    'text store' => ['agent.sources.train.text', 'POST', 'agents.sources.text.store', [], [], 422],
    'Q&A list' => ['agent.sources.train.qa', 'GET', 'agents.sources.qa.index', [], [], 200],
    'Q&A store' => ['agent.sources.train.qa', 'POST', 'agents.sources.qa.store', [], [], 422],
    'website list' => ['agent.sources.train.web', 'GET', 'agents.sources.websites.index', [], [], 200],
    'website store' => ['agent.sources.train.web', 'POST', 'agents.sources.websites.store', [], [], 422],
    'files page' => ['agent.sources.train.files', 'GET', 'agents.sources.files', [], [], 200],
    'files data' => ['agent.sources.train.files', 'GET', 'agents.sources.files.data', [], [], 200],
    'markdown page' => ['agent.sources.train.files', 'GET', 'agents.sources.markdown', [], [], 200],
    'file upload' => ['agent.sources.train.files', 'POST', 'agents.sources.files.upload', [], [], 422],
    'file processing' => ['agent.sources.train.files', 'POST', 'agents.sources.files.process', [], [], 200],
    'audio page' => ['agent.sources.train.audio', 'GET', 'agents.sources.audio', [], [], 200],
    'video page' => ['agent.sources.train.video', 'GET', 'agents.sources.video', [], [], 200],
    'web page' => ['agent.sources.train.web', 'GET', 'agents.sources.web', [], [], 200],
    // Linking a source the workspace already trained adds to what the agent
    // knows, so listing them needs that kind's training permission.
    'trained files to link' => ['agent.sources.train.files', 'GET', 'agents.sources.list', ['type' => 'pdf'], [], 200],
    'trained texts to link' => ['agent.sources.train.text', 'GET', 'agents.sources.texts.list', [], [], 200],
    'trained websites to link' => ['agent.sources.train.web', 'GET', 'agents.sources.trained-websites.index', [], [], 200],
    // Leads (an empty bulk delete deletes nothing and redirects back)
    'submissions' => ['agent.leads.view', 'GET', 'agents.forms.submissions.index', fn (): array => [FormDefinition::factory()->forAgent($this->agent)->create()], [], 200],
    // Deleting leads is managing lead capture, more than viewing them.
    'submissions delete' => ['agent.leads.settings', 'DELETE', 'agents.forms.submissions.destroy', fn (): array => [FormDefinition::factory()->forAgent($this->agent)->create()], [], 302],
    'submissions export' => ['agent.leads.export', 'GET', 'agents.forms.submissions.export', fn (): array => [FormDefinition::factory()->forAgent($this->agent)->create()], [], 200],
    'lead settings' => ['agent.leads.settings', 'PATCH', 'agents.leads.settings', [], [], 422],
    // Chat log ({conversation} is looked up by external id after the gate)
    'conversations' => ['agent.chat.view', 'GET', 'agents.logs.conversations', [], [], 200],
    'conversation delete' => ['agent.chat.delete', 'DELETE', 'agents.logs.delete-conversation', ['11111111-1111-1111-1111-111111111111'], [], 404],
    // Live chat: the waiting list shows the visitor's name, email and last
    // message, so it and mark-read need agent.chat.view, like the chat log.
    'waiting list' => ['agent.chat.view', 'GET', 'agents.handoff.pending', [], [], 200],
    'mark read' => ['agent.chat.view', 'POST', 'agents.handoff.mark-read', fn (): array => [Conversation::factory()->forAgent($this->agent)->handedOff(HandoffStatus::Pending)->create()->external_id], [], 201],
    // Analytics pages and their data, with the permission the agent menu uses to show them
    'overview page' => ['agent.analytics.chats', 'GET', 'agents.analytics', [], [], 200],
    'overview data' => ['agent.analytics.chats', 'GET', 'agents.analytics.data', [], [], 200],
    'chart data' => ['agent.analytics.chats', 'GET', 'agents.analytics.chart-data', [], [], 200],
    'sentiment page' => ['agent.analytics.sentiment', 'GET', 'agents.analytics.sentiment', [], [], 200],
    'sentiment data' => ['agent.analytics.sentiment', 'GET', 'agents.sentiment.data', [], [], 200],
    'tone page' => ['agent.analytics.tone', 'GET', 'agents.analytics.tone', [], [], 200],
    'tone data' => ['agent.analytics.tone', 'GET', 'agents.tone.data', [], [], 200],
    'topics page' => ['agent.analytics.topics', 'GET', 'agents.analytics.topics', [], [], 200],
    'topics data' => ['agent.analytics.topics', 'GET', 'agents.topics.data', [], [], 200],
    'topic suggestions' => ['agent.analytics.topics', 'GET', 'agents.topics.suggestions', [], [], 200],
    'sources page' => ['agent.analytics.sources', 'GET', 'agents.analytics.sources', [], [], 200],
    'sources data' => ['agent.analytics.sources', 'GET', 'agents.analytics.sources.data', [], [], 200],
    // Evals
    'evals page' => ['agent.evals.view', 'GET', 'agents.evals', [], [], 200],
    'eval run' => ['agent.evals.view', 'GET', 'agents.evals.runs.show', fn (): array => [EvalRun::factory()->forAgent($this->agent)->completed()->create()], [], 200],
    'cases from knowledge' => ['agent.evals.manage', 'POST', 'agents.evals.cases.from-knowledge', [], [], 422],
    // Blocked visitors and chatters
    'blocked page' => ['agent.guardrails.view', 'GET', 'agents.security.blocked', [], [], 200],
    'blocked list' => ['agent.guardrails.view', 'GET', 'agents.security.blocked.list', [], [], 200],
    // Identity
    'identity page' => ['agent.identity.manage', 'GET', 'agents.identity', [], [], 200],
    'rate-limit page' => ['agent.identity.manage', 'GET', 'agents.security.rate-limit', [], [], 200],
    // Chatters and topics
    'chatters' => ['agent.chatters.manage', 'GET', 'agents.chatters.index', [], [], 200],
    'topics' => ['agent.topics.manage', 'GET', 'agents.topics.index', [], [], 200],
]);

it('refuses a teammate without the route\'s permission and lets one with it through', function (string $permission, string $method, string $route, array $parameters, array $body, int $allowedStatus): void {
    $url = route($route, [$this->agent, ...$parameters]);

    $this->actingAs(teammateWithPermissions($this->owner, allAgentPermissionsBut($permission)))
        ->json($method, $url, $body)
        ->assertForbidden();
    $this->actingAs(teammateWithPermissions($this->owner, [$permission]))
        ->json($method, $url, $body)
        ->assertStatus($allowedStatus);
})->with('agent routes');

// Route binding resolves the text or Q&A pair before the gate, so an unknown
// id is a 404 for everyone; with a real record a blocked teammate stops at the
// gate and a permitted one fails the validation of an empty update body.
it('gates reading and editing a text or Q&A pair by its training permission', function (string $perm, string $showRoute, string $updateRoute, string $kind): void {
    $blocked = teammateWithPermissions($this->owner, []);
    $allowed = teammateWithPermissions($this->owner, [$perm]);
    $record = $kind === 'text' ? aprText($this->agent) : AgentQuestionAnswerFactory::new()->attachedTo($this->agent)->create();

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
    $website = SourceWebsite::factory()->ingested()->attachedTo($this->agent)->create();
    $page = $website->urls()->create(['url' => $website->url.'/page']);
    $blocked = teammateWithPermissions($this->owner, []);
    $trainer = teammateWithPermissions($this->owner, ['agent.sources.train.web']);
    $remover = teammateWithPermissions($this->owner, ['agent.sources.delete']);

    $this->actingAs($blocked)->getJson(route('agents.sources.websites.links.index', [$this->agent, $website]))->assertForbidden();
    $this->actingAs($trainer)->getJson(route('agents.sources.websites.links.index', [$this->agent, $website]))->assertOk();
    $this->actingAs($blocked)->postJson(route('agents.sources.websites.train', [$this->agent, $website]))->assertForbidden();
    $this->actingAs($blocked)->postJson(route('agents.sources.websites.links.retrain', [$this->agent, $website, $page]))->assertForbidden();
    $this->actingAs($trainer)->deleteJson(route('agents.sources.websites.links.destroy', [$this->agent, $website, $page]))->assertForbidden();
    $this->actingAs($trainer)->postJson(route('agents.sources.websites.links.bulk-destroy', [$this->agent, $website]), ['ids' => [$page->id]])->assertForbidden();

    expect($website->urls()->whereKey($page->id)->exists())->toBeTrue();

    $this->actingAs($remover)->deleteJson(route('agents.sources.websites.links.destroy', [$this->agent, $website, $page]))->assertSuccessful();

    expect($website->urls()->whereKey($page->id)->exists())->toBeFalse();
});

// Route binding resolves `{qa}` before the gate, so the probe uses a real row.
it('gates the Q&A delete route by agent.sources.delete', function (): void {
    $qa = AgentQuestionAnswerFactory::new()->attachedTo($this->agent)->create();
    $allowed = teammateWithPermissions($this->owner, ['agent.sources.delete']);

    $this->actingAs(teammateWithPermissions($this->owner, []))
        ->deleteJson(route('agents.sources.qa.destroy', [$this->agent, $qa->id]))
        ->assertForbidden();
    $this->actingAs($allowed)->deleteJson(route('agents.sources.qa.destroy', [$this->agent, 999999]))->assertNotFound();
    $this->actingAs($allowed)->deleteJson(route('agents.sources.qa.destroy', [$this->agent, $qa->id]))->assertOk();
});

// Unlinking takes knowledge away, so it needs agent.sources.delete, as the
// dashboard's remove dialog (unlink or delete) already does.
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

// Route binding resolves `{text}` before the gate, so the probe uses a real row.
it('gates linking a trained text by agent.sources.train.text and unlinking by agent.sources.delete', function (): void {
    $blocked = teammateWithPermissions($this->owner, []);
    $trainer = teammateWithPermissions($this->owner, ['agent.sources.train.text']);
    $remover = teammateWithPermissions($this->owner, ['agent.sources.delete']);
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

// The ids are looked up after the gate: a blocked teammate stops at it (403),
// a permitted one then fails the validation (422) or finds no such source (404).
it('gates linking a trained website by agent.sources.train.web and unlinking by agent.sources.delete', function (): void {
    $this->actingAs(teammateWithPermissions($this->owner, []))
        ->postJson(route('agents.sources.trained-websites.link', [$this->agent, 999999]), ['website_id' => 999999])
        ->assertForbidden();
    $this->actingAs(teammateWithPermissions($this->owner, ['agent.sources.train.web']))
        ->postJson(route('agents.sources.trained-websites.link', [$this->agent, 999999]), ['website_id' => 999999])
        ->assertUnprocessable();
    $this->actingAs(teammateWithPermissions($this->owner, ['agent.sources.train.web']))
        ->postJson(route('agents.sources.trained-websites.unlink', [$this->agent, 999999]))
        ->assertForbidden();
    $this->actingAs(teammateWithPermissions($this->owner, ['agent.sources.delete']))
        ->postJson(route('agents.sources.trained-websites.unlink', [$this->agent, 999999]))
        ->assertNotFound();
});

// A website is a workspace record, so `can.access.agent` lets any of the
// workspace's websites through; each route must also find it on the agent.
it('answers 404 for a website of another agent and leaves it alone', function (string $method, string $route, bool $withPage, array $body): void {
    Bus::fake();
    $website = SourceWebsite::factory()->ingested()->attachedTo(makeTeamAgent($this->owner, 'Other'))->create();
    $page = $website->urls()->create(['url' => $website->url.'/page']);
    $parameters = [$this->agent, $website, ...($withPage ? [$page] : [])];
    $body = array_map(fn (mixed $value): mixed => $value === 'PAGE_ID' ? [$page->id] : $value, $body);

    $this->actingAs($this->owner)->json($method, route($route, $parameters), $body)->assertNotFound();

    expect(SourceWebsite::query()->whereKey($website->id)->exists())->toBeTrue()
        ->and($website->fresh()->refresh_interval)->toBe($website->refresh_interval)
        ->and($website->urls()->whereKey($page->id)->exists())->toBeTrue();
    Bus::assertNothingDispatched();
})->with([
    'update' => ['PATCH', 'agents.sources.websites.update', false, ['refresh_interval' => 'weekly']],
    'delete' => ['DELETE', 'agents.sources.websites.destroy', false, []],
    'train' => ['POST', 'agents.sources.websites.train', false, ['recrawl' => true]],
    'retrain a page' => ['POST', 'agents.sources.websites.links.retrain', true, []],
    'delete a page' => ['DELETE', 'agents.sources.websites.links.destroy', true, []],
    'delete pages' => ['POST', 'agents.sources.websites.links.bulk-destroy', false, ['link_ids' => 'PAGE_ID']],
]);

it('gates a file summary by agent.sources.train.files and only shows the agent\'s own files', function (): void {
    $file = AgentFileFactory::new()->create(['workspace_id' => $this->agent->workspace_id]);
    $this->agent->files()->attach($file->id);
    $otherFile = AgentFileFactory::new()->create(['workspace_id' => $this->agent->workspace_id]);
    makeTeamAgent($this->owner, 'Other')->files()->attach($otherFile->id);
    $trainer = teammateWithPermissions($this->owner, ['agent.sources.train.files']);

    $this->actingAs(teammateWithPermissions($this->owner, allAgentPermissionsBut('agent.sources.train.files')))
        ->getJson(route('agents.agent.files.summary', [$this->agent, $file]))
        ->assertForbidden();
    $this->actingAs($trainer)->getJson(route('agents.agent.files.summary', [$this->agent, $file]))->assertOk();
    $this->actingAs($trainer)->getJson(route('agents.agent.files.summary', [$this->agent, $otherFile]))->assertNotFound();
});

// Revising an answer writes a Q&A entry, so it needs the Q&A training
// permission on top of reading the chat log.
it('revises a chat log answer only for a teammate who may also train Q&A', function (): void {
    $reply = Message::factory()->fromAssistant()->create(['chat_conversation_id' => Conversation::factory()->create(['agent_id' => $this->agent->id])->id]);
    $revision = [
        'message_id' => (string) $reply->id,
        'user_message' => 'How do I reset my password?',
        'expected_response' => 'Open Settings, then Security, then choose Reset password.',
    ];

    $this->actingAs(teammateWithPermissions($this->owner, allAgentPermissionsBut('agent.sources.train.qa')))
        ->postJson(route('agents.logs.revise-answer', $this->agent), $revision)
        ->assertForbidden();

    expect(AgentQuestionAnswer::query()->where('agent_id', $this->agent->id)->exists())->toBeFalse();

    $this->actingAs(teammateWithPermissions($this->owner, ['agent.chat.view', 'agent.sources.train.qa']))
        ->postJson(route('agents.logs.revise-answer', $this->agent), $revision)
        ->assertSuccessful();

    expect(AgentQuestionAnswer::query()->where('agent_id', $this->agent->id)->exists())->toBeTrue();
});

it('refuses a setup change to a teammate without its permission and makes it for one with it', function (string $method, string $route, string $permission, array $body, int $allowed, Closure $changed): void {
    Bus::fake([PurgeAgentJob::class]);
    $this->agent->update(['name' => 'Support Bot', 'is_public' => false]);
    $this->secretBefore = $this->agent->ensureAuthSecret();

    $this->actingAs(teammateWithPermissions($this->owner, allAgentPermissionsBut($permission), $this->agent->team))
        ->json($method, route($route, $this->agent), $body)
        ->assertForbidden();

    expect($changed(Agent::withTrashed()->findOrFail($this->agent->id)))->toBeFalse();
    Bus::assertNotDispatched(PurgeAgentJob::class);

    $this->actingAs(teammateWithPermissions($this->owner, [$permission], $this->agent->team))
        ->from(route('agents.setup', $this->agent))
        ->json($method, route($route, $this->agent), $body)
        ->assertStatus($allowed);

    expect($changed(Agent::withTrashed()->findOrFail($this->agent->id)))->toBeTrue();
})->with([
    'rename' => ['PATCH', 'agents.rename', 'agent.setup', ['name' => 'Renamed Bot', 'slug' => 'renamed-bot'], 302,
        fn (Agent $agent): bool => $agent->name === 'Renamed Bot' && $agent->slug === 'renamed-bot'],
    'update the persona' => ['PATCH', 'agents.update', 'agent.setup', ['system_instructions' => 'Answer in one line.'], 200,
        fn (Agent $agent): bool => $agent->system_instructions === 'Answer in one line.'],
    'open it to the internet' => ['PATCH', 'agents.security.update', 'agent.setup', ['is_public' => true], 302,
        fn (Agent $agent): bool => $agent->is_public === true],
    'renew the signing secret' => ['POST', 'agents.identity.regenerate', 'agent.identity.manage', [], 302,
        fn (Agent $agent): bool => $agent->auth_secret !== test()->secretBefore],
    'delete it' => ['DELETE', 'agents.delete', 'agent.delete', [], 302,
        fn (Agent $agent): bool => $agent->trashed()],
]);

it('keeps the chatters routes to a teammate with agent.chatters.manage', function (): void {
    $holder = Chatter::factory()->ofWorkspace($this->owner)->create();
    $holder->agents()->attach($this->agent->id, ['workspace_id' => $this->agent->workspace_id]);
    $granted = Chatter::factory()->ofWorkspace($this->owner)->create();
    $refused = teammateWithPermissions($this->owner, allAgentPermissionsBut('agent.chatters.manage'));
    $manager = teammateWithPermissions($this->owner, ['agent.chatters.manage']);

    $this->actingAs($refused)->postJson(route('agents.chatters.store', $this->agent), ['email' => 'new-chatter@example.test'])->assertForbidden();
    $this->actingAs($refused)->postJson(route('agents.chatters.grant', [$this->agent, $granted->external_id]))->assertForbidden();
    $this->actingAs($refused)->deleteJson(route('agents.chatters.destroy', [$this->agent, $holder->external_id]))->assertForbidden();

    expect($granted->fresh()->canAccessAgent($this->agent))->toBeFalse()
        ->and($holder->fresh()->canAccessAgent($this->agent))->toBeTrue()
        ->and(Chatter::query()->where('email', 'new-chatter@example.test')->exists())->toBeFalse();

    $this->actingAs($manager)->getJson(route('agents.chatters.index', $this->agent))->assertOk();
    $this->actingAs($manager)->post(route('agents.chatters.grant', [$this->agent, $granted->external_id]))->assertRedirect();
    $this->actingAs($manager)->delete(route('agents.chatters.destroy', [$this->agent, $holder->external_id]))->assertRedirect();

    expect($granted->fresh()->canAccessAgent($this->agent))->toBeTrue()
        ->and($holder->fresh()->canAccessAgent($this->agent))->toBeFalse();
});

it('needs agent.evals.manage to write eval cases and runs: viewing is not enough', function (): void {
    Bus::fake();
    $case = EvalCase::factory()->forAgent($this->agent)->create(['name' => 'Opening hours']);
    $run = EvalRun::factory()->forAgent($this->agent)->completed()->create();
    $viewer = teammateWithPermissions($this->owner, allAgentPermissionsBut('agent.evals.manage'));
    $manager = teammateWithPermissions($this->owner, ['agent.evals.manage']);
    $body = ['name' => 'Renamed', 'input' => 'Y', 'scorer_type' => 'contains', 'scorer_config' => ['value' => 'z']];

    $this->actingAs($viewer)->postJson(route('agents.evals.cases.store', $this->agent), $body)->assertForbidden();
    $this->actingAs($viewer)->patchJson(route('agents.evals.cases.update', [$this->agent, $case]), $body)->assertForbidden();
    $this->actingAs($viewer)->deleteJson(route('agents.evals.cases.destroy', [$this->agent, $case]))->assertForbidden();
    $this->actingAs($viewer)->postJson(route('agents.evals.runs.store', $this->agent))->assertForbidden();
    $this->actingAs($viewer)->deleteJson(route('agents.evals.runs.destroy', [$this->agent, $run]))->assertForbidden();
    $this->actingAs($viewer)->deleteJson(route('agents.evals.runs.clear', $this->agent))->assertForbidden();

    expect(EvalCase::query()->forAgent($this->agent->id)->pluck('name')->all())->toBe(['Opening hours'])
        ->and(EvalRun::query()->forAgent($this->agent->id)->pluck('id')->all())->toBe([$run->id]);
    Bus::assertNothingBatched();

    $this->actingAs($manager)->post(route('agents.evals.cases.store', $this->agent), $body)->assertRedirect();
    $this->actingAs($manager)->patch(route('agents.evals.cases.update', [$this->agent, $case]), $body)->assertRedirect();
    $this->actingAs($manager)->delete(route('agents.evals.runs.destroy', [$this->agent, $run]))->assertRedirect();
    $this->actingAs($manager)->post(route('agents.evals.runs.store', $this->agent))->assertRedirect();
    $this->actingAs($manager)->delete(route('agents.evals.cases.destroy', [$this->agent, $case]))->assertRedirect();

    expect(EvalCase::query()->forAgent($this->agent->id)->pluck('name')->all())->toBe(['Renamed'])
        ->and(EvalRun::query()->forAgent($this->agent->id)->whereKeyNot($run->id)->count())->toBe(1)
        ->and(EvalRun::query()->whereKey($run->id)->exists())->toBeFalse();
});

it('keeps renaming, removing, promoting and dismissing topics to a teammate with agent.topics.manage', function (): void {
    $topic = Topic::factory()->attachedTo($this->agent)->create(['topic' => 'Refunds']);
    aprSuggestion($this->agent, 'shipping');
    aprSuggestion($this->agent, 'spam');
    $refused = teammateWithPermissions($this->owner, allAgentPermissionsBut('agent.topics.manage'));
    $manager = teammateWithPermissions($this->owner, ['agent.topics.manage']);

    $this->actingAs($refused)->postJson(route('agents.topics.store', $this->agent), ['topic' => 'New', 'description' => 'x'])->assertForbidden();
    $this->actingAs($refused)->patchJson(route('agents.topics.update', [$this->agent, $topic]), ['topic' => 'Renamed', 'description' => 'x'])->assertForbidden();
    $this->actingAs($refused)->deleteJson(route('agents.topics.destroy', [$this->agent, $topic]))->assertForbidden();
    $this->actingAs($refused)->postJson(route('agents.topics.suggestions.promote', $this->agent), ['label' => 'shipping'])->assertForbidden();
    $this->actingAs($refused)->deleteJson(route('agents.topics.suggestions.dismiss', $this->agent), ['label' => 'shipping'])->assertForbidden();

    expect($this->agent->topics()->pluck('topics.topic')->all())->toBe(['Refunds'])
        ->and(aprSuggestions($this->agent)->pluck('suggested_label')->sort()->values()->all())->toBe(['shipping', 'spam']);

    $this->actingAs($manager)->patchJson(route('agents.topics.update', [$this->agent, $topic]), ['topic' => 'Money back', 'description' => 'x'])->assertOk();
    $this->actingAs($manager)->postJson(route('agents.topics.suggestions.promote', $this->agent), ['label' => 'shipping'])->assertOk();
    $this->actingAs($manager)->deleteJson(route('agents.topics.suggestions.dismiss', $this->agent), ['label' => 'spam'])->assertOk();
    $this->actingAs($manager)->deleteJson(route('agents.topics.destroy', [$this->agent, $topic]))->assertOk();

    expect($this->agent->topics()->pluck('topics.topic')->all())->toBe(['shipping'])
        ->and(aprSuggestions($this->agent)->exists())->toBeFalse();
});

// Replying, typing and handing back need no permission of their own, only
// holding the conversation, which takes the takeover permission.
it('keeps the live chat takeover to a teammate with agent.chat.takeover, who may then reply and hand back', function (): void {
    Event::fake([HandedOff::class]);
    $conversation = Conversation::factory()->forAgent($this->agent)->visible()->handedOff(HandoffStatus::Pending)->create();
    $holder = teammateWithPermissions($this->owner, ['agent.chat.view', 'agent.chat.takeover']);

    $this->actingAs(teammateWithPermissions($this->owner, allAgentPermissionsBut('agent.chat.takeover')))
        ->postJson(aprHandoff('agents.handoff.takeover', $this->agent, $conversation))
        ->assertForbidden();

    expect($conversation->fresh())
        ->handoff_status->toBe(HandoffStatus::Pending)
        ->handoff_user_id->toBeNull();
    Event::assertNotDispatched(HandedOff::class);

    $this->actingAs($holder)->postJson(aprHandoff('agents.handoff.takeover', $this->agent, $conversation))->assertSuccessful();
    expect($conversation->fresh()->handoff_user_id)->toBe($holder->id);

    $this->actingAs($holder)->postJson(aprHandoff('agents.handoff.message', $this->agent, $conversation), ['content' => 'On it.'])->assertSuccessful();
    $this->actingAs($holder)->postJson(aprHandoff('agents.handoff.typing', $this->agent, $conversation), ['is_typing' => true])->assertSuccessful();
    $this->actingAs($holder)->postJson(aprHandoff('agents.handoff.return', $this->agent, $conversation))->assertSuccessful();

    expect($conversation->fresh()->handoff_status)->toBe(HandoffStatus::Ai);
});

/**
 * A completed workspace text (with its stored content) for the gate probes,
 * attached to the agent unless told otherwise.
 */
function aprText(Agent $agent, bool $attach = true): SourceText
{
    $text = ($attach ? SourceText::factory()->attachedTo($agent) : SourceText::factory()->state(['workspace_id' => $agent->workspace_id]))
        ->ingested()
        ->create();

    Storage::put($text->filepath, 'Some trained text.');

    return $text;
}

/** A topic suggestion with this label on a new conversation of the agent. */
function aprSuggestion(Agent $agent, string $label): void
{
    ConversationTopic::factory()->forConversation(Conversation::factory()->create(['agent_id' => $agent->id]))->suggested($label)->create();
}

/**
 * The topic suggestions on the agent's conversations.
 *
 * @return Builder<ConversationTopic>
 */
function aprSuggestions(Agent $agent): Builder
{
    return ConversationTopic::query()
        ->whereIn('chat_conversation_id', Conversation::query()->select('id')->where('agent_id', $agent->id))
        ->whereNotNull('suggested_label');
}

function aprHandoff(string $name, Agent $agent, Conversation $conversation): string
{
    return route($name, ['agent' => $agent->external_id, 'conversation' => $conversation->external_id]);
}
