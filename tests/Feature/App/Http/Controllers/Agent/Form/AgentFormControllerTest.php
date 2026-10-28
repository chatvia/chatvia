<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Domain\Integration\Connector\Actions\Agent\EnableAgentCapabilityAction;
use Domain\Integration\Mcp\Exceptions\McpException;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;

/**
 * An agent's Forms page: the list with each form's lead count, and creating
 * and deleting forms. The page is reachable whether or not the Forms
 * capability is on, so the controller keeps the capability in step with the
 * forms: the first form turns it on, deleting the last one turns it off. A
 * form is saved even when switching the capability on fails.
 */
beforeEach(function (): void {
    // Capabilities and forms need a license key; EnsureCapabilityLicenseTest covers the gate.
    withLicense();
    $this->owner = User::factory()->create();
    $this->agent = makeTeamAgent($this->owner, 'Support Bot');

    // The workspace's Forms row exists, so enabling is a pure attach (no
    // remote tools/list during the test).
    AgentMcpServer::factory()->forOwner($this->owner)->builtin('forms')->create();
});

function afcStore(User $user, Agent $agent, string $name = 'Contact', string $fieldKey = 'email'): TestResponse
{
    return test()->actingAs($user)->post(route('agents.forms.store', $agent->external_id), [
        'name' => $name,
        'form_type' => 'lead',
        'is_active' => true,
        'schema' => [['key' => $fieldKey, 'label' => 'Field', 'type' => 'text', 'required' => true]],
    ]);
}

function afcDestroy(User $user, Agent $agent, FormDefinition $form): TestResponse
{
    return test()->actingAs($user)->delete(route('agents.forms.destroy', ['agent' => $agent->external_id, 'form' => $form->external_id]));
}

it('lists each form with its submitted-submission count', function (): void {
    $contact = FormDefinition::factory()->forAgent($this->agent)->create(['name' => 'Contact']);
    FormSubmission::factory()->forDefinition($contact)->submitted()->count(2)->create();
    FormSubmission::factory()->forDefinition($contact)->create(); // pending → not counted
    $booking = FormDefinition::factory()->forAgent($this->agent)->create(['name' => 'Booking']);
    FormSubmission::factory()->forDefinition($booking)->submitted()->create();

    $this->actingAs($this->owner)
        ->get(route('agents.forms.index', $this->agent->external_id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('dashboard/agents/pages/Forms/index')
            ->where('forms.0.name', 'Booking')
            ->where('forms.0.submissions_count', 1)
            ->where('forms.1.submissions_count', 2));
});

it('enables the Forms capability when the first form is created', function (): void {
    expect($this->agent->isFormsEnabled())->toBeFalse();

    afcStore($this->owner, $this->agent)->assertRedirect();

    expect($this->agent->isFormsEnabled())->toBeTrue();
});

it('disables the Forms capability when the last form is deleted', function (): void {
    afcStore($this->owner, $this->agent)->assertRedirect();

    afcDestroy($this->owner, $this->agent, FormDefinition::query()->forAgent($this->agent)->sole())->assertRedirect();

    expect($this->agent->isFormsEnabled())->toBeFalse();
});

it('keeps the Forms capability enabled while other forms remain', function (): void {
    afcStore($this->owner, $this->agent, 'Contact', 'email');
    afcStore($this->owner, $this->agent, 'Booking', 'date');

    afcDestroy($this->owner, $this->agent, FormDefinition::query()->forAgent($this->agent)->orderBy('id')->firstOrFail())->assertRedirect();

    expect($this->agent->isFormsEnabled())->toBeTrue();
});

it('deletes a form with its leads and keeps the other forms\' leads', function (): void {
    $form = FormDefinition::factory()->forAgent($this->agent)->create();
    FormSubmission::factory()->forDefinition($form)->submitted()->count(2)->create();
    $kept = FormSubmission::factory()->forDefinition(FormDefinition::factory()->forAgent($this->agent)->create())->submitted()->create();

    afcDestroy($this->owner, $this->agent, $form)->assertRedirect();

    expect(FormSubmission::query()->where('form_definition_id', $form->id)->exists())->toBeFalse()
        ->and(FormSubmission::query()->whereKey($kept->id)->exists())->toBeTrue();
});

it('still saves the form and reports the error when enabling Forms fails', function (): void {
    Exceptions::fake();
    $this->mock(EnableAgentCapabilityAction::class)
        ->shouldReceive('execute')
        ->andThrow(new McpException('tools/list failed'));

    afcStore($this->owner, $this->agent)->assertRedirect();

    expect(FormDefinition::query()->forAgent($this->agent)->count())->toBe(1);
    Exceptions::assertReported(McpException::class);
});

it('refuses to change or delete the form a capability manages', function (): void {
    $managed = FormDefinition::factory()->forAgent($this->agent)->create(['managed_by' => 'calendar']);

    afcDestroy($this->owner, $this->agent, $managed)->assertForbidden();
    $this->actingAs($this->owner)
        ->put(route('agents.forms.update', ['agent' => $this->agent->external_id, 'form' => $managed->external_id]), [
            'name' => 'Renamed',
            'form_type' => 'lead',
            'is_active' => true,
            'schema' => [['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true]],
        ])
        ->assertForbidden();

    expect($managed->fresh()->name)->toBe($managed->name);
});

it('keeps the Forms capability off when only a managed form is left', function (): void {
    afcStore($this->owner, $this->agent)->assertRedirect();
    FormDefinition::factory()->forAgent($this->agent)->create(['managed_by' => 'calendar']);

    afcDestroy($this->owner, $this->agent, FormDefinition::query()->forAgent($this->agent)->unmanaged()->sole())->assertRedirect();

    expect($this->agent->isFormsEnabled())->toBeFalse();
});
