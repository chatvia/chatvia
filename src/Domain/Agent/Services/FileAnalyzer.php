<?php

namespace Domain\Agent\Services;

use App\Documents\PdfInfo;
use App\Media\AudioDurationExtractor;
use Domain\Source\File\Data\FileAnalysisData;
use Illuminate\Http\UploadedFile;
use LogicException;

class FileAnalyzer
{
    public static function analyze(UploadedFile|string $file): FileAnalysisData
    {
        $filePath = $file instanceof UploadedFile ? $file->getPathname() : $file;

        $pdfInfo = app(PdfInfo::class);

        if (is_pdf_file($filePath)) {
            return $pdfInfo->getInfo($filePath);
        }

        if (is_word_file($filePath)) {
            $pdf = office()->toPdf($filePath);

            return $pdfInfo->getInfo($pdf);
        }

        if (is_text_file($filePath)) {
            $pdf = office()->toPdf($filePath);
            $info = $pdfInfo->getInfo($pdf);
            $info->length *= 3;

            return $info;
        }

        // Audio
        if (is_audio_file($filePath) || is_video_file($filePath)) {
            $durationExtractor = app(AudioDurationExtractor::class);
            $minutes = $durationExtractor->getDurationInMinutes($filePath);

            return new FileAnalysisData(
                length: $minutes,
                metadata: [],
            );
        }

        throw new LogicException('Invalid file type!');
    }
}
