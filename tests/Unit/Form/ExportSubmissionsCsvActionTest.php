<?php

use App\Identity\Workspace\Models\User;
use Domain\Form\Actions\ExportSubmissionsCsvAction;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Domain\Membership\Team\Services\TeamService;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->user);
    $this->agent = makeTeamAgent($this->user, 'Bot');
    $this->form = FormDefinition::factory()->forAgent($this->agent)->create();
});

it('exports submissions as CSV with field-label headers and decrypted values', function (): void {
    FormSubmission::factory()->forDefinition($this->form)->submitted()->create([
        'values' => ['name' => 'Jane', 'email' => 'jane@acme.com'],
    ]);

    $csv = app(ExportSubmissionsCsvAction::class)->execute($this->form);

    expect($csv)->toContain('Email')          // label header
        ->toContain('Submitted at')
        ->toContain('jane@acme.com')           // decrypted value
        ->toContain('Jane');
});

it('escapes formula-injection in visitor values', function (): void {
    FormSubmission::factory()->forDefinition($this->form)->submitted()->create([
        'values' => ['name' => '=HYPERLINK(1)', 'email' => 'x@y.com'],
    ]);

    $csv = app(ExportSubmissionsCsvAction::class)->execute($this->form);

    expect($csv)->toContain("'=HYPERLINK(1)");  // leading quote neutralizes the formula
});
