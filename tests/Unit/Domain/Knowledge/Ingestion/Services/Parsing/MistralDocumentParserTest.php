<?php

use Domain\Knowledge\Ingestion\Services\Clients\MistralOcr;
use Domain\Knowledge\Ingestion\Services\Parsing\LocalDocumentParser;
use Domain\Knowledge\Ingestion\Services\Parsing\MistralDocumentParser;

/**
 * Mistral OCR gets only the formats it documents (PDF, Word, PowerPoint and
 * common images). Excel, CSV, old office formats and RTF are read by the
 * app, as Mistral does not list them.
 */
beforeEach(function (): void {
    $this->ocr = Mockery::mock(MistralOcr::class);
    $this->local = Mockery::mock(LocalDocumentParser::class);
    app()->instance(MistralOcr::class, $this->ocr);
    app()->instance(LocalDocumentParser::class, $this->local);
});

it('sends Mistral the formats it reads', function (string $extension): void {
    $this->ocr->shouldReceive('fromFile')->once()->andReturn(collect());
    $this->local->shouldNotReceive('parse');

    app(MistralDocumentParser::class)->parse("/tmp/file.{$extension}", 'tmp');
})->with(['pdf', 'docx', 'pptx', 'png', 'webp']);

it('reads the other office formats with the app', function (string $extension): void {
    $this->ocr->shouldNotReceive('fromFile');
    $this->local->shouldReceive('parse')->once()->andReturn(collect());

    app(MistralDocumentParser::class)->parse("/tmp/file.{$extension}", 'tmp');
})->with(['xlsx', 'csv', 'doc', 'ppt', 'rtf']);
