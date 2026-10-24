<?php

use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * An agent's inline forms through the public API: creating one (its slug
 * comes from the name), showing it, updating it partially (the slug never
 * changes) and deleting it with its submissions. A field of an unknown type
 * is a field error.
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

it('updates partially: an omitted field keeps its value and the slug never changes', function (): void {
    $id = $this->withToken($this->token)->postJson($this->uri, fcBody())->json('data.id');

    $this->withToken($this->token)
        ->patchJson("{$this->uri}/{$id}", ['name' => 'Talk to sales', 'is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.name', 'Talk to sales')
        ->assertJsonPath('data.slug', 'contact-us')
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.schema.0.key', 'name');
});

it('rejects a schema field of an unknown type', function (): void {
    $this->withToken($this->token)
        ->postJson($this->uri, [...fcBody(), 'schema' => [['key' => 'file', 'label' => 'File', 'type' => 'upload']]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['schema.0.type']);
});

it('deletes a form with its submissions', function (): void {
    $form = FormDefinition::factory()->forAgent($this->agent)->create();
    FormSubmission::factory()->forDefinition($form)->submitted()->create();

    $this->withToken($this->token)->deleteJson("{$this->uri}/{$form->external_id}")->assertNoContent();

    expect($form->fresh())->toBeNull()
        ->and(FormSubmission::query()->where('form_definition_id', $form->id)->exists())->toBeFalse();
});
