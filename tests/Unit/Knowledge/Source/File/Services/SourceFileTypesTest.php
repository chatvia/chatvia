<?php

declare(strict_types=1);

use Domain\Knowledge\Source\File\Enums\KnowledgeFileType;
use Domain\Knowledge\Source\File\Services\SourceFileTypes;
use Domain\Llm\Enums\AiProvider;

/**
 * The knowledge base recognises documents by extension (MIME is unreliable for
 * the ebook formats: .fb2 → xml/text), so validation, routing and metering all
 * key off the file extension through SourceFileTypes.
 */
beforeEach(function (): void {
    // PDF, Word, slides and images need a document reader.
    connectAiProviders(AiProvider::Mistral);
});

it('accepts every document extension the ingestion reads while every type is on', function (): void {
    foreach (SourceFileTypes::documentExtensions() as $ext) {
        expect(SourceFileTypes::isSupported('file.'.$ext))->toBeTrue("$ext should be supported");
    }

    expect(SourceFileTypes::isSupported('malware.exe'))->toBeFalse()
        ->and(SourceFileTypes::isSupported('archive.zip'))->toBeFalse();
});

it('groups exactly the document extensions the ingestion reads into file types', function (): void {
    $grouped = collect(KnowledgeFileType::cases())
        ->reject(fn (KnowledgeFileType $type): bool => $type->isMedia())
        ->flatMap(fn (KnowledgeFileType $type): array => $type->extensions());

    expect($grouped->duplicates()->all())->toBe([])
        ->and($grouped->sort()->values()->all())->toBe(collect(SourceFileTypes::documentExtensions())->sort()->values()->all());
});

it('accepts only the file types an admin turned on', function (): void {
    // Video needs a speech-to-text service.
    config(['chatvia.knowledge.file_types' => ['word', 'video', 'unknown'], 'chatvia.ai.transcriber' => 'scaleway']);

    expect(KnowledgeFileType::enabled())->toBe([KnowledgeFileType::Word, KnowledgeFileType::Video])
        ->and(SourceFileTypes::isSupported('a.docx'))->toBeTrue()
        ->and(SourceFileTypes::isSupported('a.pdf'))->toBeFalse()
        ->and(SourceFileTypes::isDocument('a.pdf'))->toBeTrue()
        ->and(SourceFileTypes::isSupportedMedia('video/mp4'))->toBeTrue()
        ->and(SourceFileTypes::isSupportedMedia('audio/mpeg'))->toBeFalse();
});

it('detects image files by extension only', function (): void {
    expect(SourceFileTypes::isImage('photo.png'))->toBeTrue()
        ->and(SourceFileTypes::isImage('scan.HEIC'))->toBeTrue()
        ->and(SourceFileTypes::isImage('frame.avif'))->toBeTrue()
        ->and(SourceFileTypes::isImage('doc.pdf'))->toBeFalse()
        ->and(SourceFileTypes::isImage('book.epub'))->toBeFalse();
});

it('maps extensions to the metering category stored on filetype', function (): void {
    expect(SourceFileTypes::category('a.pdf'))->toBe('pdf')
        ->and(SourceFileTypes::category('a.docx'))->toBe('word')
        ->and(SourceFileTypes::category('a.doc'))->toBe('word')
        ->and(SourceFileTypes::category('a.rtf'))->toBe('word')
        ->and(SourceFileTypes::category('a.pptx'))->toBe('presentation')
        ->and(SourceFileTypes::category('a.xlsx'))->toBe('spreadsheet')
        ->and(SourceFileTypes::category('a.csv'))->toBe('spreadsheet')
        ->and(SourceFileTypes::category('a.txt'))->toBe('text')
        ->and(SourceFileTypes::category('a.md'))->toBe('markdown')
        ->and(SourceFileTypes::category('a.png'))->toBe('image')
        ->and(SourceFileTypes::category('a.heic'))->toBe('image')
        ->and(SourceFileTypes::category('a.epub'))->toBe('document')
        ->and(SourceFileTypes::category('a.fb2'))->toBe('document');
});

it('maps audio and video MIME types to their category and rejects unknown ones', function (): void {
    expect(SourceFileTypes::categoryOfMimeType('audio/mpeg'))->toBe('audio')
        ->and(SourceFileTypes::categoryOfMimeType('video/mp4'))->toBe('video')
        ->and(fn (): string => SourceFileTypes::categoryOfMimeType('application/x-unknown'))
        ->toThrow(RuntimeException::class, 'Unsupported mime type');
});
