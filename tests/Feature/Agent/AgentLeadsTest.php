<?php

use App\Identity\Workspace\Models\User;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Domain\Membership\Team\Services\TeamService;

/**
 * An agent's leads: deleting the submissions picked on a form's list, and
 * saving which fields the lead capture asks for. The permission gates are in
 * AgentPermissionRouteTest.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->agent = createAgent($this->owner);
    $this->form = FormDefinition::factory()->forAgent($this->agent)->create();
});

it('deletes the picked submissions of the form and keeps the rest', function (): void {
    [$picked, $alsoPicked, $kept] = FormSubmission::factory()->forDefinition($this->form)->submitted()->count(3)->create();
    $otherForm = FormDefinition::factory()->forAgent($this->agent)->create();
    $ofOtherForm = FormSubmission::factory()->forDefinition($otherForm)->submitted()->create();

    $this->actingAs($this->owner)
        ->from(route('agents.forms.submissions.index', [$this->agent, $this->form]))
        ->delete(route('agents.forms.submissions.destroy', [$this->agent, $this->form]), [
            // A submission of another form of the agent is not touched, even when named.
            'external_ids' => [$picked->external_id, $alsoPicked->external_id, $ofOtherForm->external_id],
        ])
        ->assertRedirect(route('agents.forms.submissions.index', [$this->agent, $this->form]));

    expect(FormSubmission::query()->whereKey([$picked->id, $alsoPicked->id])->exists())->toBeFalse()
        ->and(FormSubmission::query()->whereKey($kept->id)->exists())->toBeTrue()
        ->and(FormSubmission::query()->whereKey($ofOtherForm->id)->exists())->toBeTrue();
});

it('404s a form of another agent', function (): void {
    $foreignForm = FormDefinition::factory()->forAgent(createAgent($this->owner))->create();
    $submission = FormSubmission::factory()->forDefinition($foreignForm)->submitted()->create();

    $this->actingAs($this->owner)
        ->deleteJson(route('agents.forms.submissions.destroy', [$this->agent, $foreignForm]), ['external_ids' => [$submission->external_id]])
        ->assertNotFound();

    expect(FormSubmission::query()->whereKey($submission->id)->exists())->toBeTrue();
});

it('saves the lead fields in order, with whether each is required', function (): void {
    $this->actingAs($this->owner)
        ->patchJson(route('agents.leads.settings', $this->agent), ['fields' => [
            ['title' => 'Email', 'required' => true],
            ['title' => 'Company', 'required' => '0'],
        ]])
        ->assertSuccessful();

    expect($this->agent->fresh()->settings('leads')->get('fields'))->toBe([
        ['title' => 'Email', 'required' => true],
        ['title' => 'Company', 'required' => false],
    ]);
});

it('refuses lead fields without a title or a required flag', function (): void {
    $this->actingAs($this->owner)
        ->patchJson(route('agents.leads.settings', $this->agent), ['fields' => [['required' => true], ['title' => 'Phone']]])
        ->assertJsonValidationErrors(['fields.0.title', 'fields.1.required']);
});
