<?php

use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * A form's leads through the public API: only the submitted entries, newest
 * first, with their values. A form still being filled in is never listed.
 */
uses(ActsAsApiOwner::class);

beforeEach(function (): void {
    $this->actAsApiOwner();
});

it('lists only the submitted entries of the form, with their values', function (): void {
    $form = FormDefinition::factory()->forAgent($this->agent)->create();
    FormSubmission::factory()->forDefinition($form)->create();
    $older = FormSubmission::factory()->forDefinition($form)->submitted()->create(['values' => ['name' => 'Ada', 'email' => 'ada@example.test']]);
    $newer = FormSubmission::factory()->forDefinition($form)->submitted()->create(['values' => ['name' => 'Grace', 'email' => 'grace@example.test']]);

    $this->withToken($this->token)
        ->getJson("/api/v1/agents/{$this->agent->external_id}/forms/{$form->external_id}/submissions")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $newer->external_id)
        ->assertJsonPath('data.0.values.name', 'Grace')
        ->assertJsonPath('data.1.id', $older->external_id)
        ->assertJsonPath('data.1.submitted_at', fn (?string $at): bool => $at !== null);
});
