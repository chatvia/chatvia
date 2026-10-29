<?php

use Domain\Knowledge\Document\Conversion\Markdown\CompoundFile;
use Illuminate\Support\Str;
use Tests\Support\Knowledge\Document\OldOffice\CompoundFileWriter;

/**
 * The OLE compound file reader under the .doc and .ppt readers: streams in
 * regular sectors and in the mini stream, looked up by name, and a damaged
 * file (a sector chain pointing back at itself) refused rather than read in
 * a loop.
 */
it('reads large and small streams by name, ignoring case', function (): void {
    $large = Str::repeat('Large stream. ', 700);
    $file = CompoundFile::open(tempTestFile('file.cfb', CompoundFileWriter::write(['WordDocument' => $large, 'Small' => 'A few bytes.', 'Empty' => ''])));

    expect($file->read('worddocument'))->toBe($large)
        ->and($file->read('SMALL'))->toBe('A few bytes.')
        ->and($file->read('Empty'))->toBe('')
        ->and($file->has('Small'))->toBeTrue()
        ->and($file->has('Missing'))->toBeFalse()
        ->and($file->read('Missing'))->toBeNull();

    $file->close();
});

it('refuses a sector chain that loops instead of reading it forever', function (): void {
    $bytes = CompoundFileWriter::write(['Big' => Str::repeat('x', 5000)]);
    $fatSector = unpack('V', $bytes, 0x4C)[1];
    $bytes = substr_replace($bytes, pack('V', 0), 512 + $fatSector * 512, 4);
    $file = CompoundFile::open(tempTestFile('file.cfb', $bytes));

    expect(fn (): ?string => $file->read('Big'))->toThrow(RuntimeException::class, 'broken sector chain');

    $file->close();
});
