<?php

use Domain\Knowledge\Document\Conversion\Markdown\CompoundFile;
use Tests\Support\Knowledge\Document\OldOffice\CompoundFileWriter;

/**
 * The OLE compound file reader under the .doc and .ppt readers: streams in
 * regular sectors and in the mini stream, looked up by name, and a damaged
 * file refused rather than read in a loop.
 */
function cfbFile(string $bytes): string
{
    return tempTestFile('file.cfb', $bytes);
}

it('reads large and small streams by name, ignoring case', function (): void {
    $large = str_repeat('Large stream. ', 700);
    $path = cfbFile(CompoundFileWriter::write(['WordDocument' => $large, 'Small' => 'A few bytes.', 'Empty' => '']));
    $file = CompoundFile::open($path);

    expect($file->read('worddocument'))->toBe($large)
        ->and($file->read('SMALL'))->toBe('A few bytes.')
        ->and($file->read('Empty'))->toBe('')
        ->and($file->has('Small'))->toBeTrue()
        ->and($file->has('Missing'))->toBeFalse()
        ->and($file->read('Missing'))->toBeNull();

    $file->close();
});

it('tells a compound file from any other file and refuses to open the other', function (): void {
    $compound = cfbFile(CompoundFileWriter::write(['Stream' => 'text']));
    $other = cfbFile('{\rtf1 not a compound file}');

    expect(CompoundFile::isCompoundFile($compound))->toBeTrue()
        ->and(CompoundFile::isCompoundFile($other))->toBeFalse()
        ->and(fn (): CompoundFile => CompoundFile::open($other))->toThrow(RuntimeException::class, 'not an OLE compound file');
});

it('refuses a sector chain that loops instead of reading it forever', function (): void {
    $bytes = CompoundFileWriter::write(['Big' => str_repeat('x', 5000)]);

    // The stream starts at sector 0; point that sector's table entry back at itself.
    $fatSector = unpack('V', $bytes, 0x4C)[1];
    $bytes = substr_replace($bytes, pack('V', 0), 512 + $fatSector * 512, 4);
    $path = cfbFile($bytes);
    $file = CompoundFile::open($path);

    expect(fn (): ?string => $file->read('Big'))->toThrow(RuntimeException::class, 'broken sector chain');

    $file->close();
});
