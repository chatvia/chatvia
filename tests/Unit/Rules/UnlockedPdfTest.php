<?php

use App\Http\Rules\UnlockedPdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Validator;

/**
 * A PDF locked with a password is refused at upload with that reason: no
 * reader can open it. pdfinfo tells, as it refuses such a file.
 */
function pdfUploadForLockCheck(): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'pdf').'.pdf';
    file_put_contents($path, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF");

    return new UploadedFile($path, 'manual.pdf', 'application/pdf', null, true);
}

it('refuses a PDF locked with a password', function (): void {
    Process::fake(['*' => Process::result(output: '', errorOutput: 'Command Line Error: Incorrect password', exitCode: 1)]);

    $validator = Validator::make(['file' => pdfUploadForLockCheck()], ['file' => [new UnlockedPdf]]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('file'))->toBe('This PDF is locked with a password. Remove the password and upload it again.');
});

it('lets an open PDF through', function (): void {
    Process::fake(['*' => Process::result("Pages:          3\n")]);

    expect(Validator::make(['file' => pdfUploadForLockCheck()], ['file' => [new UnlockedPdf]])->passes())->toBeTrue();
});
