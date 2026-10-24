<?php

use App\Http\Rules\MaxPdfPages;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Validator;
use Tests\Support\Files\FakePdf;

/**
 * MaxPdfPages caps the pages of a chat-attachment PDF, as pdfinfo counts
 * them. A PDF pdfinfo cannot read fails with a generic reason, unless it is
 * locked (UnlockedPdf gives that reason). Anything else is skipped.
 */
function mppValidator(mixed $file): Illuminate\Validation\Validator
{
    return Validator::make(['file' => $file], ['file' => [new MaxPdfPages(max: 10)]]);
}

it('fails when the PDF has more pages than the limit', function (): void {
    Process::fake(['*' => Process::result("Pages:          11\n")]);

    $validator = mppValidator(FakePdf::upload());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('file'))->toBe('This PDF has 11 pages — the limit is 10.');
});

it('passes when the PDF is within the page limit', function (): void {
    Process::fake(['*' => Process::result("Pages:          10\n")]);

    expect(mppValidator(FakePdf::upload())->passes())->toBeTrue();
});

it('fails with a generic reason when pdfinfo cannot read the PDF', function (): void {
    Process::fake(['*' => Process::result(output: '', errorOutput: "Syntax Error: Couldn't read xref table", exitCode: 1)]);

    $validator = mppValidator(FakePdf::upload());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('file'))->toBe('We couldn\'t read this PDF. Please try a different file.');
});

it('leaves a locked PDF to UnlockedPdf', function (): void {
    Process::fake(['*' => Process::result(output: '', errorOutput: 'Command Line Error: Incorrect password', exitCode: 1)]);

    expect(mppValidator(FakePdf::upload())->passes())->toBeTrue();
});

it('skips values that are not PDFs without running pdfinfo', function (string|UploadedFile $value): void {
    Process::fake();

    expect(mppValidator($value)->passes())->toBeTrue();

    Process::assertNothingRan();
})->with([
    'a string' => ['not-a-file'],
    'a text file' => [fn (): UploadedFile => UploadedFile::fake()->createWithContent('notes.txt', 'Hello')],
]);
