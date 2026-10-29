<?php

use App\Identity\Workspace\Models\User;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;

/**
 * Deleting the leads picked on a form's list: only that form's picked leads
 * go, even when a lead of another form is named.
 */
beforeEach(function (): void {
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
            'external_ids' => [$picked->external_id, $alsoPicked->external_id, $ofOtherForm->external_id],
        ])
        ->assertRedirect(route('agents.forms.submissions.index', [$this->agent, $this->form]));

    expect(FormSubmission::query()->whereKey([$picked->id, $alsoPicked->id])->exists())->toBeFalse()
        ->and(FormSubmission::query()->whereKey([$kept->id, $ofOtherForm->id])->count())->toBe(2);
});
