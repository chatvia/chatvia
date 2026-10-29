<?php

use App\Http\Rules\MaxPdfPages;
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

it('refuses a PDF over the page limit and lets one within it through', function (): void {
    Process::fake(['*' => Process::sequence()
        ->push(Process::result("Pages:          11\n"))
        ->push(Process::result("Pages:          10\n"))]);

    $over = mppValidator(FakePdf::upload());
    $overFails = $over->fails();
    $withinPasses = mppValidator(FakePdf::upload())->passes();

    expect($overFails)->toBeTrue()
        ->and($over->errors()->first('file'))->toContain('11')
        ->and($withinPasses)->toBeTrue();
});

it('refuses a PDF pdfinfo cannot read, but leaves a locked one to UnlockedPdf', function (): void {
    Process::fake(['*' => Process::sequence()
        ->push(Process::result(output: '', errorOutput: "Syntax Error: Couldn't read xref table", exitCode: 1))
        ->push(Process::result(output: '', errorOutput: "Syntax Error: Couldn't read xref table", exitCode: 1))
        ->push(Process::result(output: '', errorOutput: 'Command Line Error: Incorrect password', exitCode: 1))
        ->push(Process::result(output: '', errorOutput: 'Command Line Error: Incorrect password', exitCode: 1))]);

    $unreadableFails = mppValidator(FakePdf::upload())->fails();
    $lockedPasses = mppValidator(FakePdf::upload())->passes();

    expect($unreadableFails)->toBeTrue()
        ->and($lockedPasses)->toBeTrue();
});
