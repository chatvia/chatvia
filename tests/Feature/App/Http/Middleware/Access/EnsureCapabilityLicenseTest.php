<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Form\Enums\FormSubmissionStatus;
use Domain\Form\Events\FormSubmitted;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Conversation\HostedChatVisitor;

/**
 * The `license.capabilities` route middleware: without a license key the
 * capabilities and the forms answer 403 (building forms, their leads, turning
 * a capability on, submitting a form in a chat), and the Forms page opens
 * without the forms. Any paid key (Pro or Enterprise) opens them again, with
 * the forms and leads made earlier.
 */
beforeEach(function (): void {
    Event::fake([FormSubmitted::class]);

    $this->owner = User::factory()->create();
    $this->agent = makeTeamAgent($this->owner, 'Support Bot');
    $this->form = FormDefinition::factory()->forAgent($this->agent)->create(['name' => 'Contact']);

    // The workspace's Forms row exists, so enabling is a pure attach.
    AgentMcpServer::factory()->forOwner($this->owner)->builtin('forms')->create();
});

function eclStoreForm(User $user, Agent $agent): TestResponse
{
    return test()->actingAs($user)->post(route('agents.forms.store', $agent->external_id), [
        'name' => 'Demo request',
        'form_type' => 'lead',
        'is_active' => true,
        'schema' => [['key' => 'email', 'label' => 'Email', 'type' => 'text', 'required' => true]],
    ]);
}

/** A public agent that rendered an inline form in a visitor's hosted chat. */
function eclRenderedForm(): FormSubmission
{
    $agent = Agent::factory()->create(['is_public' => true]);
    $conversationId = HostedChatVisitor::startConversation($agent, 'I want a demo');

    return FormSubmission::factory()->forDefinition(FormDefinition::factory()->forAgent($agent)->create())->create([
        'chat_conversation_id' => Conversation::query()->where('external_id', $conversationId)->value('id'),
    ]);
}

function eclSubmit(FormSubmission $form): TestResponse
{
    return test()->postJson("/chat/{$form->agent->slug}/forms/{$form->external_id}/submit", [
        'fields' => ['name' => 'Ada Lovelace', 'email' => 'ada@example.test'],
    ]);
}

it('refuses building forms, their leads and the capabilities without a license key', function (): void {
    $this->actingAs($this->owner);

    eclStoreForm($this->owner, $this->agent)->assertForbidden();
    $this->get(route('agents.forms.submissions.index', ['agent' => $this->agent->external_id, 'form' => $this->form->external_id]))->assertForbidden();
    $this->get(route('agents.forms.submissions.export', ['agent' => $this->agent->external_id, 'form' => $this->form->external_id]))->assertForbidden();
    $this->postJson(route('agents.capabilities.enable', ['agent' => $this->agent->external_id, 'slug' => 'forms']))
        ->assertForbidden()
        ->assertJsonPath('message', 'This feature is unavailable. Please contact your administrator.');

    expect(FormDefinition::query()->where('name', 'Demo request')->exists())->toBeFalse()
        ->and($this->agent->isFormsEnabled())->toBeFalse();
});

it('opens the Forms page without the forms without a license key', function (): void {
    $this->actingAs($this->owner)
        ->get(route('agents.forms.index', $this->agent->external_id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('agents/Forms/index')
            ->has('forms', 0));

    expect($this->form->fresh())->not->toBeNull();
});

it('refuses a form submitted in a chat without a license key', function (): void {
    $form = eclRenderedForm();

    eclSubmit($form)->assertForbidden();

    expect($form->fresh()->status)->toBe(FormSubmissionStatus::Pending);
    Event::assertNotDispatched(FormSubmitted::class);
});

it('opens forms, leads and capabilities with a paid license key', function (Closure $license): void {
    $license();

    $this->actingAs($this->owner)
        ->get(route('agents.forms.index', $this->agent->external_id))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('forms.0.name', 'Contact'));

    eclStoreForm($this->owner, $this->agent)->assertRedirect();
    $this->get(route('agents.forms.submissions.index', ['agent' => $this->agent->external_id, 'form' => $this->form->external_id]))->assertOk();

    expect($this->agent->isFormsEnabled())->toBeTrue();

    $form = eclRenderedForm();
    eclSubmit($form)->assertOk();
    expect($form->fresh()->status)->toBe(FormSubmissionStatus::Submitted);
})->with([
    'Pro' => [fn () => withLicense()],
    'Enterprise' => [fn () => withEnterpriseLicense()],
]);
