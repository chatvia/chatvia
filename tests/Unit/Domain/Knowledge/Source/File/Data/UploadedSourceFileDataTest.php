<?php

declare(strict_types=1);

use Domain\Knowledge\Source\File\Data\UploadedSourceFileData;
use Domain\Knowledge\Source\File\Enums\KnowledgeFileType;

/**
 * An uploaded file knows its knowledge file type, the extension its stored
 * copy gets, and whether the knowledge base takes it now: only while the
 * admin has that type on.
 */
it('accepts audio only while the admin has audio on', function (): void {
    // Audio needs a speech-to-text service.
    config(['chatvia.ai.transcriber' => 'scaleway']);
    $upload = new UploadedSourceFileData(
        path: tempTestFile('note.mp3', 'ID3'),
        originalName: 'note.mp3',
        mimeType: 'audio/mpeg',
        clientMimeType: 'audio/mpeg',
        sizeInBytes: 3,
    );

    expect($upload->isSupported())->toBeTrue()
        ->and($upload->category())->toBe(KnowledgeFileType::Audio);

    config(['chatvia.knowledge.file_types' => ['pdf', 'video']]);

    expect($upload->isSupported())->toBeFalse();
});

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
