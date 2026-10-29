<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Testing\TestResponse;

/**
 * Creating and deleting an agent's forms. The page is reachable whether or
 * not the Forms capability is on, so the controller keeps the capability in
 * step with the forms: the first form turns it on, deleting the last one
 * (with its leads) turns it off. A form a capability manages cannot be
 * changed or deleted.
 */
beforeEach(function (): void {
    withLicense();
    $this->owner = User::factory()->create();
    $this->agent = makeTeamAgent($this->owner, 'Support Bot');
    AgentMcpServer::factory()->forOwner($this->owner)->builtin('forms')->create();
});

function afcDestroy(User $user, Agent $agent, FormDefinition $form): TestResponse
{
    return test()->actingAs($user)->delete(route('agents.forms.destroy', ['agent' => $agent->external_id, 'form' => $form->external_id]));
}

it('turns Forms on with the first form and off when the last form goes with its leads', function (): void {
    $this->actingAs($this->owner)->post(route('agents.forms.store', $this->agent->external_id), [
        'name' => 'Contact',
        'form_type' => 'lead',
        'is_active' => true,
        'schema' => [['key' => 'email', 'label' => 'Field', 'type' => 'text', 'required' => true]],
    ])->assertRedirect();
    $form = FormDefinition::query()->forAgent($this->agent)->sole();
    FormSubmission::factory()->forDefinition($form)->submitted()->count(2)->create();

    expect($this->agent->isFormsEnabled())->toBeTrue();

    afcDestroy($this->owner, $this->agent, $form)->assertRedirect();

    expect($this->agent->isFormsEnabled())->toBeFalse()
        ->and(FormSubmission::query()->where('form_definition_id', $form->id)->exists())->toBeFalse();
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
