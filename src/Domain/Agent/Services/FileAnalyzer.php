<?php

namespace Domain\Agent\Services;

use Domain\Knowledge\Document\Pandoc;
use Domain\Knowledge\Document\PdfInfo;
use Domain\Knowledge\Ingestion\Services\Media\AudioDurationExtractor;
use Domain\Knowledge\Source\File\Data\FileAnalysisData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use LogicException;
use Throwable;

/**
 * Computes a file's "length" at upload time for quota enforcement — pages for
 * documents/images, minutes for audio/video. Mistral OCR returns the true page
 * count only during ingestion, so this is a pre-flight estimate:
 *
 *   - PDF            → pdfinfo (exact)
 *   - Office/text    → LibreOffice → PDF → pdfinfo (exact)
 *   - Images         → 1 page
 *   - Pandoc-only    → extract text, ~500 words per page (estimate)
 *   - Audio/Video    → duration in minutes
 */
class FileAnalyzer
{
    /** Average words per page used to estimate page count from extracted text. */
    private const WORDS_PER_PAGE = 500;

    public static function analyze(UploadedFile|string $file): FileAnalysisData
    {
        $filePath = $file instanceof UploadedFile ? $file->getPathname() : $file;
        $extension = file_extension($file);

        // Audio / video → duration in minutes.
        if (is_audio_file($filePath) || is_video_file($filePath)) {
            $minutes = app(AudioDurationExtractor::class)->getDurationInMinutes($filePath);

            return new FileAnalysisData(length: $minutes, metadata: []);
        }

        // PDF → exact page count.
        if ($extension === 'pdf' || is_pdf_file($filePath)) {
            return app(PdfInfo::class)->getInfo($filePath);
        }

        // Images → one page.
        if (in_array($extension, config('mimes.ocr.image'), true)) {
            return new FileAnalysisData(length: 1, metadata: []);
        }

        // LibreOffice-convertible (Word, PowerPoint, Excel, ODT, RTF, txt, html)
        // → render to PDF and count pages exactly.
        if (in_array($extension, config('mimes.ocr.office'), true)) {
            // PHP stores uploads in an extensionless temp file (/tmp/phpXXXXXX).
            // LibreOffice picks its import filter from the extension, so the raw
            // upload path makes it silently produce nothing (exit 0, no PDF).
            // Copy to a temp file that keeps the original extension first.
            $input = $file instanceof UploadedFile
                ? create_temp_file("source.{$extension}", File::get($filePath))
                : $filePath;

            $pdf = office()->toPdf($input);

            return app(PdfInfo::class)->getInfo($pdf);
        }

        // Pandoc-only text formats → estimate pages from the extracted word count.
        if (in_array($extension, config('mimes.ocr.pandoc'), true)) {
            return new FileAnalysisData(
                length: self::estimatePagesFromText($filePath, $extension),
                metadata: [],
            );
        }

        throw new LogicException('Invalid file type!');
    }

    private static function estimatePagesFromText(string $filePath, string $extension): int
    {
        try {
            $words = str_word_count(app(Pandoc::class)->toText($filePath, $extension));

            return max(1, (int) ceil($words / self::WORDS_PER_PAGE));
        } catch (Throwable) {
            // Best-effort: Pandoc can't always parse the input (e.g. a JATS file
            // read as DocBook). Fall back to a rough size-based estimate so the
            // upload still proceeds; Mistral OCR reports the true count later.
            $bytes = File::size($filePath) ?: 0;

            return max(1, (int) ceil($bytes / 3000));
        }
    }
}
