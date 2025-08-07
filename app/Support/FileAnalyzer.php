<?php

namespace App\Support;

use App\Data\PdfInfoData;
use Illuminate\Http\UploadedFile;

class FileAnalyzer
{
    public static function analyze(UploadedFile|string $file): PdfInfoData
    {
        $pdfInfo = app(PdfInfo::class);

        $file = $file instanceof UploadedFile ? $file->getPathname() : $file;

        if (is_pdf_file($file)) {
            $info = $pdfInfo->getInfo($file);
        }

        if (is_word_file($file)) {
            $pdf = office()->toPdf($file);
            $info = $pdfInfo->getInfo($pdf);
        }

        if (is_text_file($file)) {
            $pdf = office()->toPdf($file);
            $info = $pdfInfo->getInfo($pdf);
            $info->length *= 3;
        }

        return $info;
    }
}
