<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Services\Parsing;

use Domain\Knowledge\Document\Conversion\ReaderImage;
use Domain\Knowledge\Ingestion\Contracts\DocumentParser;
use Domain\Knowledge\Ingestion\Services\Clients\MistralOcr;
use Domain\Knowledge\Ingestion\Services\DocumentReaders;
use Domain\Knowledge\Source\File\Services\SourceFileTypes;
use Illuminate\Support\Collection;

/**
 * Document parser backed by Mistral's hosted OCR: reads PDFs, Word,
 * PowerPoint and images, including the images and tables inside them
 * ({@see DocumentReaders::MISTRAL_READS}). An image in another format (HEIC)
 * is sent as a PNG. Excel, CSV, old office formats and RTF are read by the
 * app: Mistral does not list them.
 */
class MistralDocumentParser implements DocumentParser
{
    public function __construct(
        private readonly MistralOcr $ocr,
        private readonly LocalDocumentParser $local,
        private readonly ReaderImage $images,
    ) {}

    public function parse(string $filePath, string $storageDirectory): Collection
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (DocumentReaders::readsItself('mistral', $extension)) {
            return $this->ocr->fromFile($filePath);
        }

        if (SourceFileTypes::isImage($filePath)) {
            $png = $this->images->png($filePath);

            try {
                return $this->ocr->fromFile($png);
            } finally {
                @unlink($png);
            }
        }

        return $this->local->parse($filePath, $storageDirectory);
    }

    /** The OCR model, such as "mistral-ocr-latest". */
    public function name(): string
    {
        return (string) config('services.mistral.ocr_model');
    }
}
