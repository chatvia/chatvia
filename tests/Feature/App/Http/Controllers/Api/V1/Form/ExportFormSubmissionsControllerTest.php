<?php

use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * A form's leads as a CSV download through the public API: only the ones
 * `ids[]` names. What the CSV holds (labels, formula escaping) is
 * ExportSubmissionsCsvAction's and is tested there.
 */
uses(ActsAsApiOwner::class);

it('downloads only the submissions ids[] names as CSV', function (): void {
    $this->actAsApiOwner();
    $this->form = FormDefinition::factory()->forAgent($this->agent)->create();
    $this->uri = "/api/v1/agents/{$this->agent->external_id}/forms/{$this->form->external_id}/submissions/export";
    $chosen = FormSubmission::factory()->forDefinition($this->form)->submitted()->create(['values' => ['name' => 'Grace', 'email' => 'grace@example.test']]);
    FormSubmission::factory()->forDefinition($this->form)->submitted()->create(['values' => ['name' => 'Ada', 'email' => 'ada@example.test']]);

    $response = $this->withToken($this->token)
        ->get("{$this->uri}?ids[]={$chosen->external_id}")
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    expect($response->getContent())->toContain('grace@example.test')->not->toContain('ada@example.test');
});
