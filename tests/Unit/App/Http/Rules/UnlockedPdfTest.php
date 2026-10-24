<?php

use App\Http\Rules\UnlockedPdf;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Validator;
use Tests\Support\Files\FakePdf;

/**
 * A PDF locked with a password is refused at upload with that reason: no
 * reader can open it. pdfinfo tells, as it refuses such a file.
 */
it('refuses a PDF locked with a password', function (): void {
    Process::fake(['*' => Process::result(output: '', errorOutput: 'Command Line Error: Incorrect password', exitCode: 1)]);

    $validator = Validator::make(['file' => FakePdf::upload('manual.pdf')], ['file' => [new UnlockedPdf]]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('file'))->toBe('This PDF is locked with a password. Remove the password and upload it again.');
});

it('lets an open PDF through', function (): void {
    Process::fake(['*' => Process::result("Pages:          3\n")]);

    expect(Validator::make(['file' => FakePdf::upload('manual.pdf')], ['file' => [new UnlockedPdf]])->passes())->toBeTrue();
});
