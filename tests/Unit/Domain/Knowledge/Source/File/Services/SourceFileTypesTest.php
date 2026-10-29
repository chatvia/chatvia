<?php

declare(strict_types=1);

use Domain\Knowledge\Source\File\Enums\KnowledgeFileType;
use Domain\Knowledge\Source\File\Services\SourceFileTypes;
use Domain\Llm\Enums\AiProvider;

/**
 * The knowledge base recognises documents by extension (MIME is unreliable for
 * the ebook formats), so validation, routing and metering key off the file
 * extension through SourceFileTypes, and only the types an admin turned on
 * are taken. Media is recognised by its MIME type.
 */
beforeEach(function (): void {
    connectAiProviders(AiProvider::Mistral);
    config(['services.bin.pandoc' => '/bin/sh', 'services.bin.catdoc' => '/bin/sh', 'services.bin.catppt' => '/bin/sh']);
});

it('accepts only the file types an admin turned on', function (): void {
    config(['chatvia.knowledge.file_types' => ['word', 'video', 'unknown'], 'chatvia.ai.transcriber' => 'scaleway']);

    expect(KnowledgeFileType::enabled())->toBe([KnowledgeFileType::Word, KnowledgeFileType::Video])
        ->and(SourceFileTypes::isSupported('a.docx'))->toBeTrue()
        ->and(SourceFileTypes::isSupported('a.pdf'))->toBeFalse()
        ->and(SourceFileTypes::isDocument('a.pdf'))->toBeTrue()
        ->and(SourceFileTypes::isSupportedMedia('video/mp4'))->toBeTrue()
        ->and(SourceFileTypes::isSupportedMedia('audio/mpeg'))->toBeFalse()
        ->and(SourceFileTypes::isSupported('malware.exe'))->toBeFalse()
        ->and(SourceFileTypes::isSupported('archive.zip'))->toBeFalse();
});

it('maps audio and video MIME types to their category and rejects unknown ones', function (): void {
    expect(SourceFileTypes::categoryOfMimeType('audio/mpeg'))->toBe(KnowledgeFileType::Audio)
        ->and(SourceFileTypes::categoryOfMimeType('video/mp4'))->toBe(KnowledgeFileType::Video)
        ->and(fn (): string => SourceFileTypes::categoryOfMimeType('application/x-unknown'))
        ->toThrow(RuntimeException::class, 'Unsupported mime type');
});
