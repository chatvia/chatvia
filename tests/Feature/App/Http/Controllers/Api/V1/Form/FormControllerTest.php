<?php

use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * An agent's inline forms through the public API: creating one (its slug
 * comes from the name) and deleting it with its submissions.
 */
uses(ActsAsApiOwner::class);

beforeEach(function (): void {
    $this->actAsApiOwner();
    $this->uri = "/api/v1/agents/{$this->agent->external_id}/forms";
});

/** @return array<string, mixed> a valid lead form */
function fcBody(): array
{
    return [
        'name' => 'Contact us',
        'form_type' => 'lead',
        'schema' => [
            ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
            ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
        ],
        'lead_field_map' => ['name' => 'name', 'email' => 'email'],
    ];
}

it('creates a form with a slug from its name and shows it back', function (): void {
    $created = $this->withToken($this->token)
        ->postJson($this->uri, fcBody())
        ->assertCreated()
        ->assertJsonPath('data.slug', 'contact-us')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.submissions_count', 0);

    $this->withToken($this->token)
        ->getJson("{$this->uri}/".$created->json('data.id'))
        ->assertOk()
        ->assertJsonPath('data.name', 'Contact us')
        ->assertJsonPath('data.schema.1.key', 'email');
});

it('deletes a form with its submissions', function (): void {
    $form = FormDefinition::factory()->forAgent($this->agent)->create();
    FormSubmission::factory()->forDefinition($form)->submitted()->create();

    $this->withToken($this->token)->deleteJson("{$this->uri}/{$form->external_id}")->assertNoContent();

    expect($form->fresh())->toBeNull()
        ->and(FormSubmission::query()->where('form_definition_id', $form->id)->exists())->toBeFalse();
});
