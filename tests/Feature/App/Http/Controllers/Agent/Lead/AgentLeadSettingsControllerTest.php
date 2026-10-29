<?php

use App\Identity\Workspace\Models\User;

/**
 * The lead capture's fields: saved in order with whether each is required.
 */
beforeEach(function (): void {
    withLicense();
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner);
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
