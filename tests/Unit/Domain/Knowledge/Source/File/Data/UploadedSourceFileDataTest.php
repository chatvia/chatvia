<?php

declare(strict_types=1);

use Domain\Knowledge\Source\File\Data\UploadedSourceFileData;

/**
 * The extension an uploaded file's stored copy gets: from a document's name,
 * never from the sniffed content (a .md file holding HTML stays .md), and
 * from the type of audio.
 */
it('takes the stored extension from a document name and from the type of audio', function (): void {
    $document = new UploadedSourceFileData(
        path: tempTestFile('page.md', '<html><script>alert(1)</script></html>'),
        originalName: 'page.md',
        mimeType: 'text/html',
        clientMimeType: 'text/markdown',
        sizeInBytes: 10,
    );
    $audio = new UploadedSourceFileData(
        path: tempTestFile('voice', 'ID3'),
        originalName: 'voice',
        mimeType: 'audio/mpeg',
        clientMimeType: 'audio/mpeg',
        sizeInBytes: 3,
    );

    expect($document->storedExtension())->toBe('md')
        ->and($audio->storedExtension())->toBe('mp3');
});
