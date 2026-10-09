<?php

namespace Domain\Knowledge\Ingestion\Contracts;

use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Illuminate\Support\Collection;

/**
 * Reads an uploaded document (PDF, office file or image) into plain text, one
 * PageData per page, for knowledge ingestion. The implementation is chosen
 * from config (`chatvia.ai.document_parser`), so an install can use a hosted
 * OCR service or the local command-line tools.
 */
interface DocumentParser
{
    /**
     * @param  string  $filePath  Local path of the document; its extension names the format.
     * @param  string  $storageDirectory  The owner's storage folder, for any short-lived copy the parser must upload.
     * @return Collection<int, PageData>
     */
    public function parse(string $filePath, string $storageDirectory): Collection;

    /** Short name saved on the source as `parser` (for example "mistral-ocr" or "local"). */
    public function name(): string;
}
