<?php

use Domain\Knowledge\Source\File\Data\FileAnalysisData;
use Domain\Knowledge\Source\File\Services\FileAnalyzer;

/**
 * FileAnalyzer routes a file to a "length" (pages or minutes) for quota at
 * upload time. The Office/PDF/Pandoc branches shell out to external binaries
 * and are exercised manually; here we cover the binary-free routing: images
 * count as one page, and a genuinely unsupported type is rejected.
 */

/** Write a real 1x1 PNG so File::mimeType() reports image/png. */
function tempPng(): string
{
    $path = tempnam(sys_get_temp_dir(), 'fa_').'.png';
    file_put_contents($path, base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
    ));

    return $path;
}

it('counts an image as a single page', function (): void {
    $path = tempPng();

    $info = app(FileAnalyzer::class)->analyze($path);

    expect($info)->toBeInstanceOf(FileAnalysisData::class)
        ->and($info->length)->toBe(1);

    @unlink($path);
});

it('rejects a file type that is neither OCR-supported nor media', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'fa_').'.xyz';
    file_put_contents($path, 'just some bytes');

    expect(fn (): FileAnalysisData => app(FileAnalyzer::class)->analyze($path))->toThrow(LogicException::class);

    @unlink($path);
});
