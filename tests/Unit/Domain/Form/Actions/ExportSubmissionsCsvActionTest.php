<?php

use App\Identity\Workspace\Models\User;
use Domain\Form\Actions\ExportSubmissionsCsvAction;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Illuminate\Support\Str;

/**
 * A form's leads as CSV: submitted answers under the field labels, only the
 * chosen ones, newest first, and never a value a spreadsheet would run as a
 * formula.
 */
beforeEach(function (): void {
    $this->freezeTime();
    $this->user = User::factory()->create();
    $this->agent = makeTeamAgent($this->user, 'Bot');
    // Default factory schema: Name, Email, Message.
    $this->form = FormDefinition::factory()->forAgent($this->agent)->create();
});

/**
 * The CSV parsed back into rows, read with the same escape character it was
 * written with.
 *
 * @return list<list<string|null>>
 */
function escvRows(string $csv): array
{
    return Str::of($csv)
        ->rtrim("\n")
        ->explode("\n")
        ->map(static fn (string $line): array => str_getcsv($line, escape: '\\'))
        ->all();
}

function escvSubmitted(FormDefinition $form, string $name, string $email = 'x@y.com'): FormSubmission
{
    return FormSubmission::factory()->forDefinition($form)->submitted()->create([
        'values' => ['name' => $name, 'email' => $email],
    ]);
}

it('exports submitted answers as CSV under the field labels', function (): void {
    escvSubmitted($this->form, 'Jane', 'jane@acme.com');

    $rows = escvRows(app(ExportSubmissionsCsvAction::class)->execute($this->form));

    expect($rows)->toBe([
        ['Name', 'Email', 'Message', 'Submitted at'],
        ['Jane', 'jane@acme.com', '', now()->toDateTimeString()],
    ]);
});

it('exports only the chosen submissions, newest first', function (): void {
    $first = escvSubmitted($this->form, 'Ann');
    escvSubmitted($this->form, 'Bob');
    $third = escvSubmitted($this->form, 'Cid');

    $rows = escvRows(app(ExportSubmissionsCsvAction::class)->execute($this->form, [$first->external_id, $third->external_id]));

    expect(array_column(array_slice($rows, 1), 0))->toBe(['Cid', 'Ann']);
});

it('leaves out pending submissions and other forms\' submissions', function (): void {
    escvSubmitted($this->form, 'Jane');
    FormSubmission::factory()->forDefinition($this->form)->create(['values' => ['name' => 'Pending']]);
    escvSubmitted(FormDefinition::factory()->forAgent($this->agent)->create(), 'Other form');

    $rows = escvRows(app(ExportSubmissionsCsvAction::class)->execute($this->form));

    expect(array_column(array_slice($rows, 1), 0))->toBe(['Jane']);
});

it('neutralizes a visitor value that a spreadsheet would run as a formula', function (string $value): void {
    escvSubmitted($this->form, $value);

    $rows = escvRows(app(ExportSubmissionsCsvAction::class)->execute($this->form));

    // A leading quote makes the spreadsheet show the cell as text.
    expect($rows[1][0])->toBe("'".$value);
})->with([
    'equals' => '=HYPERLINK(1)',
    'plus' => '+1+1',
    'minus' => '-1+1',
    'at' => '@SUM(A1)',
    'tab' => "\t=1+1",
    'carriage return' => "\r=1+1",
]);
