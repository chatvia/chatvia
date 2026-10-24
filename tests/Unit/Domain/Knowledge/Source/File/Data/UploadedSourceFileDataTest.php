<?php

declare(strict_types=1);

use Domain\Knowledge\Source\File\Data\UploadedSourceFileData;
use Domain\Knowledge\Source\File\Enums\KnowledgeFileType;

/**
 * An uploaded file knows its knowledge file type, and whether the knowledge
 * base takes it now: only while the admin has that type on.
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
