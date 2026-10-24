<?php

use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * A form's leads as a CSV download through the public API, all of them or
 * the ones `ids[]` names. What the CSV holds (labels, formula escaping) is
 * ExportSubmissionsCsvAction's and is tested there.
 */
uses(ActsAsApiOwner::class);

beforeEach(function (): void {
    $this->actAsApiOwner();
    $this->form = FormDefinition::factory()->forAgent($this->agent)->create();
    $this->uri = "/api/v1/agents/{$this->agent->external_id}/forms/{$this->form->external_id}/submissions/export";
});

it('downloads every submission as CSV', function (): void {
    FormSubmission::factory()->forDefinition($this->form)->submitted()->create(['values' => ['name' => 'Ada', 'email' => 'ada@example.test']]);

    $response = $this->withToken($this->token)
        ->get($this->uri)
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    expect($response->getContent())
        ->toContain('Name,Email,Message,"Submitted at"')
        ->toContain('ada@example.test');
});

it('downloads only the submissions ids[] names', function (): void {
    $chosen = FormSubmission::factory()->forDefinition($this->form)->submitted()->create(['values' => ['name' => 'Grace', 'email' => 'grace@example.test']]);
    FormSubmission::factory()->forDefinition($this->form)->submitted()->create(['values' => ['name' => 'Ada', 'email' => 'ada@example.test']]);

    $response = $this->withToken($this->token)->get("{$this->uri}?ids[]={$chosen->external_id}")->assertOk();

    expect($response->getContent())->toContain('grace@example.test')->not->toContain('ada@example.test');
});

it('rejects a selection that is not a list of uuids', function (): void {
    $this->withToken($this->token)
        ->getJson("{$this->uri}?ids[]=nope")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ids.0']);
});
