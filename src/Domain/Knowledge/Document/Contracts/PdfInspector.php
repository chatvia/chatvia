<?php

declare(strict_types=1);

namespace Domain\Knowledge\Document\Contracts;

use Domain\Knowledge\Source\File\Data\FileAnalysisData;

/**
 * Reads a PDF's properties (page count, lock state) without parsing its
 * content. The seam lets consumers outside Knowledge (chat attachments,
 * upload rules) take a fake in tests instead of shelling out to poppler.
 */
interface PdfInspector
{
    public function getInfo(string $pdfPath): FileAnalysisData;

    /** Whether the PDF needs a password to open. */
    public function isLocked(string $pdfPath): bool;
}
