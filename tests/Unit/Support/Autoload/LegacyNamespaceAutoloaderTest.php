<?php

use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Support\Autoload\LegacyNamespaceAutoloader;

/**
 * Namespaces renamed only in letter case (`QA` → `Qa`, `OCR` → `Ocr`) are still
 * spelled the old way in queued payloads, caches and a shipped migration. On a
 * case-sensitive file system the old path no longer exists, so the loader maps
 * the old spelling to the current class.
 */
it('maps the old spellings to the current class names', function (): void {
    expect(LegacyNamespaceAutoloader::currentName('Domain\Knowledge\Source\QA\Models\AgentQuestionAnswer'))
        ->toBe('Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer')
        ->and(LegacyNamespaceAutoloader::currentName('Domain\Knowledge\Ingestion\Data\OCR\PageData'))
        ->toBe('Domain\Knowledge\Ingestion\Data\Ocr\PageData')
        ->and(LegacyNamespaceAutoloader::currentName('Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer'))->toBeNull();
});

it('unserializes a page queued under the old namespace spelling', function (): void {
    $legacy = str_replace(
        'Domain\Knowledge\Ingestion\Data\Ocr\PageData',
        'Domain\Knowledge\Ingestion\Data\OCR\PageData',
        serialize(new PageData(pageNumber: 3, content: 'Old page', checksum: 'c')),
    );

    $page = unserialize($legacy);

    expect($page)->toBeInstanceOf(PageData::class)
        ->and($page->content)->toBe('Old page');
});
