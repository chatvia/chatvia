<?php

declare(strict_types=1);

namespace Tests\Support\Files;

use Illuminate\Http\UploadedFile;

/**
 * The smallest file that reads as a PDF by its header, for the checks that
 * look at a PDF's type before they ask pdfinfo about it. It has no pages:
 * a test fakes what pdfinfo answers.
 */
final class FakePdf
{
    public const string CONTENT = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF";

    public static function upload(string $name = 'doc.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, self::CONTENT);
    }
}
