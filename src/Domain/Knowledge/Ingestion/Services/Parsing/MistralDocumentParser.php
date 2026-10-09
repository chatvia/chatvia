<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Services\Parsing;

use Domain\Knowledge\Ingestion\Contracts\DocumentParser;
use Domain\Knowledge\Ingestion\Services\Clients\MistralOcr;
use Illuminate\Support\Collection;

/**
 * Document parser backed by Mistral's hosted OCR. Reads PDFs, office files and
 * images, including the images and tables inside them.
 */
class MistralDocumentParser implements DocumentParser
{
    public function __construct(
        private readonly MistralOcr $ocr,
    ) {}

    public function parse(string $filePath, string $storageDirectory): Collection
    {
        return $this->ocr->fromFile($filePath, $storageDirectory);
    }

    public function name(): string
    {
        return 'mistral-ocr';
    }
}
