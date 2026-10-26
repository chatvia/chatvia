<?php

use App\Identity\Workspace\Models\User;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;

/**
 * Deleting the leads picked on a form's list: only that form's picked leads
 * go, and a form of another agent of the workspace is a 404.
 */
beforeEach(function (): void {
    // Capabilities and forms need a license key; EnsureCapabilityLicenseTest covers the gate.
    withLicense();
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner);
    $this->form = FormDefinition::factory()->forAgent($this->agent)->create();
});

it('deletes the picked submissions of the form and keeps the rest', function (): void {
    [$picked, $alsoPicked, $kept] = FormSubmission::factory()->forDefinition($this->form)->submitted()->count(3)->create();
    $ofOtherForm = FormSubmission::factory()->forDefinition(FormDefinition::factory()->forAgent($this->agent)->create())->submitted()->create();

    $this->actingAs($this->owner)
        ->from(route('agents.forms.submissions.index', [$this->agent, $this->form]))
        ->delete(route('agents.forms.submissions.destroy', [$this->agent, $this->form]), [
            // A submission of another form of the agent is not touched, even when named.
            'external_ids' => [$picked->external_id, $alsoPicked->external_id, $ofOtherForm->external_id],
        ])
        ->assertRedirect(route('agents.forms.submissions.index', [$this->agent, $this->form]));

    expect(FormSubmission::query()->whereKey([$picked->id, $alsoPicked->id])->exists())->toBeFalse()
        ->and(FormSubmission::query()->whereKey([$kept->id, $ofOtherForm->id])->count())->toBe(2);
});

it('404s a form of another agent', function (): void {
    $foreignForm = FormDefinition::factory()->forAgent(createAgent($this->owner))->create();
    $submission = FormSubmission::factory()->forDefinition($foreignForm)->submitted()->create();

    $this->actingAs($this->owner)
        ->deleteJson(route('agents.forms.submissions.destroy', [$this->agent, $foreignForm]), ['external_ids' => [$submission->external_id]])
        ->assertNotFound();

    expect(FormSubmission::query()->whereKey($submission->id)->exists())->toBeTrue();
});
